<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;

/**
 * `tables.status` — DENORMALIZATSIYA (docs/05-PHASE0-PLAN.md §2.6).
 *
 * ⚠️ Bu YAGONA yozuvchi. Boshqa hech qayerda `$table->status = ...`
 * yozilmaydi, aks holda status session/order holatidan uzilib qoladi.
 */
class TableStatusService
{
    /** Hisoblab, DB'ga yozadi. */
    public function recalculate(Table $table): TableStatus
    {
        $status = $this->calculate($table);

        if ($table->status !== $status) {
            $table->forceFill(['status' => $status])->save();
        }

        return $status;
    }

    /**
     * Stol holati session va uning orderlaridan kelib chiqadi
     * (docs/01-ARCHITECTURE.md §3). Tartib MUHIM — birinchi mos kelgani
     * qaytadi.
     */
    public function calculate(Table $table): TableStatus
    {
        $session = TableSession::query()
            ->where('table_id', $table->id)
            ->whereIn('status', [SessionStatus::ACTIVE->value, SessionStatus::WAITING_PAYMENT->value])
            ->orderByDesc('id')
            ->first();

        if ($session === null) {
            return TableStatus::AVAILABLE;
        }

        if ($session->status === SessionStatus::WAITING_PAYMENT) {
            return TableStatus::WAITING_PAYMENT;
        }

        $statuses = Order::query()
            ->where('session_id', $session->id)
            // DRAFT hech qanday holatga ta'sir qilmaydi.
            ->where('status', '!=', OrderStatus::DRAFT->value)
            ->pluck('status')
            ->all();

        if ($statuses === []) {
            return TableStatus::ACTIVE;
        }

        $has = static fn (OrderStatus ...$wanted): bool => array_intersect(
            array_map(static fn (OrderStatus $s): string => $s->value, $wanted),
            array_map(static fn ($s): string => $s instanceof OrderStatus ? $s->value : (string) $s, $statuses),
        ) !== [];

        // Yangi buyurtma kutilmoqda — admin ko'rishi kerak.
        if ($has(OrderStatus::PENDING, OrderStatus::ACCEPTED, OrderStatus::WAITING_FOR_WAITER)) {
            return TableStatus::ORDER_PENDING;
        }

        // Afitsant biriktirilgan va ish ustida.
        if ($has(OrderStatus::ASSIGNED, OrderStatus::WAITER_ACCEPTED, OrderStatus::DELIVERING)) {
            return TableStatus::WAITER_ASSIGNED;
        }

        if ($has(OrderStatus::DELIVERED)) {
            return TableStatus::DELIVERED;
        }

        // Faqat bekor qilingan/muddati o'tgan orderlar qolgan.
        return TableStatus::ACTIVE;
    }
}
