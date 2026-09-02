<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Order yaratish va status boshqaruvi — docs/01-ARCHITECTURE.md §8.
 *
 * QAT'IY QOIDALAR (CLAUDE.md §2.6, §2.7):
 *   · Narx HAR DOIM DB'dan qayta hisoblanadi
 *   · Frontend yuborgan `price` BUTUNLAY e'tiborsiz qoldiriladi
 *   · Total frontendda hisoblanmaydi
 */
class OrderService
{
    public function __construct(
        private readonly OrderNumberService $numbers,
        private readonly SessionService $sessions,
        private readonly TableStatusService $tableStatus,
    ) {}

    /**
     * docs/01-ARCHITECTURE.md §8 dagi transaction — aynan shu tartibda.
     *
     * @param  list<array{product_id: int, quantity: int, note?: string|null}>  $items
     */
    public function createOrder(
        Table $table,
        string $clientOrderUuid,
        array $items,
        ?TableSession $session = null,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($table, $clientOrderUuid, $items, $session, $note): Order {
            // 2. Idempotency: shu uuid bilan order allaqachon bormi?
            //    (CLAUDE.md §3.1 — tugmani ikki marta bosish 1 ta order)
            $existing = $this->findByClientUuid($table->restaurant_id, $clientOrderUuid);

            if ($existing !== null) {
                return $existing;
            }

            // 3-4. Stolning ochiq sessioni.
            $session ??= $this->sessions->findActiveSession($table, locking: true);

            // 5. Yetkazilmagan order bormi (CLAUDE.md §2.4).
            //    DRAFT bu tekshiruvga KIRMAYDI.
            if ($session !== null
                && $session->status === SessionStatus::ACTIVE
                && $this->hasUndeliveredOrder($session)) {
                throw new BusinessException('ORDER_NOT_DELIVERED', 409);
            }

            // WAITING_PAYMENT → order DRAFT sifatida saqlanadi
            // (docs/01-ARCHITECTURE.md §12).
            $isDraft = $session === null || $session->status !== SessionStatus::ACTIVE;

            // 6-7. Mahsulotlar va NARXLAR — faqat DB'dan.
            $lines = $this->resolveLines($items);

            // 8. Order va order_items.
            $order = $this->persist($table, $session, $clientOrderUuid, $lines, $isDraft, $note);

            // 9. Session summasi yangilanadi (draft session'ga tegmaydi).
            if (! $isDraft && $session !== null) {
                $this->refreshSessionTotal($session);
                $this->tableStatus->recalculate($table);
            }

            return $order;
        });
    }

    /** Status o'zgarishi — matritsa `OrderStatus` enumida (docs/01 §3). */
    public function changeStatus(Order $order, OrderStatus $target): Order
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new BusinessException('INVALID_STATUS_TRANSITION', 422);
        }

        return DB::transaction(function () use ($order, $target): Order {
            $order->forceFill(array_filter([
                'status' => $target,
                'accepted_at' => $target === OrderStatus::ACCEPTED ? now() : null,
                'assigned_at' => $target === OrderStatus::ASSIGNED ? now() : null,
                'waiter_accepted_at' => $target === OrderStatus::WAITER_ACCEPTED ? now() : null,
                'delivered_at' => $target === OrderStatus::DELIVERED ? now() : null,
                'cancelled_at' => $target === OrderStatus::CANCELLED ? now() : null,
            ], static fn (mixed $value): bool => $value !== null))->save();

            if ($order->table !== null) {
                $this->tableStatus->recalculate($order->table);
            }

            return $order->refresh();
        });
    }

    /**
     * DRAFT orderni yangi sessionga biriktiradi — to'lovdan keyin
     * (docs/01-ARCHITECTURE.md §12, PHASE 12 da ishlatiladi).
     *
     * ⚠️ NARXLAR QAYTA HISOBLANADI: cart uzoq turgan bo'lishi mumkin.
     */
    public function attachDraftToSession(Order $draft, TableSession $session): Order
    {
        if (! $draft->status->isDraft()) {
            throw new BusinessException('INVALID_STATUS_TRANSITION', 422);
        }

        return DB::transaction(function () use ($draft, $session): Order {
            $items = $draft->items->map(static fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'note' => $item->note,
            ])->all();

            $lines = $this->resolveLines($items);

            $draft->items()->delete();
            $this->writeItems($draft, $lines);

            $numbering = $this->numbers->next($session->restaurant);

            $draft->forceFill([
                'session_id' => $session->id,
                'status' => OrderStatus::PENDING,
                'guest_count' => $session->guest_count,
                'draft_expires_at' => null,
                'business_date' => $numbering['business_date'],
                'order_number' => $numbering['order_number'],
            ] + $this->totals($lines))->save();

            $this->refreshSessionTotal($session);
            $this->tableStatus->recalculate($session->table);

            return $draft->refresh();
        });
    }

    public function findByClientUuid(int $restaurantId, string $uuid): ?Order
    {
        return Order::withoutGlobalScopes()
            ->with('items')
            ->where('restaurant_id', $restaurantId)
            ->where('client_order_uuid', $uuid)
            ->first();
    }

    /** DRAFT hisobga OLINMAYDI — u ro'yxatda ham, blokda ham yo'q. */
    public function hasUndeliveredOrder(TableSession $session): bool
    {
        return Order::query()
            ->where('session_id', $session->id)
            ->open()
            ->exists();
    }

    /**
     * Mahsulotlarni DB'dan olib, narxni QAYTA hisoblaydi.
     *
     * Frontend yuborgan `price` bu yerga umuman yetib kelmaydi —
     * `CreateOrderRequest` uni tashlab yuboradi, bu metod esa faqat
     * `product_id` va `quantity` bilan ishlaydi.
     *
     * @param  list<array{product_id: int, quantity: int, note?: string|null}>  $items
     * @return list<array{product: Product, quantity: int, note: string|null, unit_price: float, subtotal: float, discount: float}>
     */
    private function resolveLines(array $items): array
    {
        $quantities = [];
        $notes = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + max(1, (int) $item['quantity']);
            $notes[$productId] ??= $item['note'] ?? null;
        }

        $products = Product::query()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null || ! $product->is_active) {
                throw new BusinessException('PRODUCT_UNAVAILABLE', 422);
            }

            if (! $product->is_available) {
                throw new BusinessException('PRODUCT_UNAVAILABLE', 422);
            }

            // Chegirma SERVERDA hisoblanadi: products.discount FOIZ,
            // natija esa SUMMA (javob 6).
            $unitPrice = $product->price;
            $effective = $product->effectivePrice();

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'note' => $notes[$productId],
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
                'discount' => round(($unitPrice - $effective) * $quantity, 2),
            ];
        }

        if ($lines === []) {
            throw new BusinessException('PRODUCT_UNAVAILABLE', 422);
        }

        return $lines;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function totals(array $lines): array
    {
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $discount = round(array_sum(array_column($lines, 'discount')), 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => round($subtotal - $discount, 2),
        ];
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function persist(
        Table $table,
        ?TableSession $session,
        string $clientOrderUuid,
        array $lines,
        bool $isDraft,
        ?string $note,
    ): Order {
        $numbering = $this->numbers->next($table->restaurant);

        $attributes = [
            'restaurant_id' => $table->restaurant_id,
            'table_id' => $table->id,
            // DRAFT: session_id = NULL (docs/01 §12).
            'session_id' => $isDraft ? null : $session?->id,
            'client_order_uuid' => $clientOrderUuid,
            'order_number' => $numbering['order_number'],
            'business_date' => $numbering['business_date'],
            'status' => $isDraft ? OrderStatus::DRAFT : OrderStatus::PENDING,
            'guest_count' => $session?->guest_count ?? 1,
            'note' => $note,
            'draft_expires_at' => $isDraft
                ? now()->addMinutes(config('smart_restaurant.draft_ttl_minutes'))
                : null,
        ] + $this->totals($lines);

        try {
            $order = Order::create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            // Parallel so'rov bizdan oldin ulgurdi (bir xil uuid).
            // Qulflovchi o'qish shart: oddiy SELECT bu transaction
            // snapshot'ida uni ko'rmaydi (docs/07-DB-DECISIONS.md §6).
            $existing = Order::withoutGlobalScopes()
                ->with('items')
                ->where('restaurant_id', $table->restaurant_id)
                ->where('client_order_uuid', $clientOrderUuid)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        $this->writeItems($order, $lines);

        return $order->load('items');
    }

    /**
     * SNAPSHOT — nom (ru+uz) va narx buyurtma paytidagi holicha
     * saqlanadi (CLAUDE.md §3.3). Mahsulot keyin o'zgarsa ham chek
     * va hisobot buzilmaydi.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function writeItems(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            $order->items()->create([
                'product_id' => $product->id,
                'product_name_ru_snapshot' => $product->name_ru,
                'product_name_uz_snapshot' => $product->name_uz,
                'price_snapshot' => $product->effectivePrice(),
                'quantity' => $line['quantity'],
                'subtotal' => round($line['subtotal'] - $line['discount'], 2),
                'note' => $line['note'],
            ]);
        }
    }

    private function refreshSessionTotal(TableSession $session): void
    {
        // SUM SQL'da — pul PHP'da yig'ilmaydi (docs/07-DB-DECISIONS.md §5).
        $total = Order::query()
            ->where('session_id', $session->id)
            ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::DRAFT->value])
            ->sum('total');

        $session->forceFill(['total_amount' => $total])->save();
    }
}
