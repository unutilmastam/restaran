<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Avtomatik afitsant biriktirish — docs/01-ARCHITECTURE.md §7 algoritmi.
 *
 *   1. status = FREE bo'lgan waiterlarni ol (shu restaurant_id bo'yicha)
 *   2. Eng kam active orderga ega bo'lganini tanla
 *   3. Teng bo'lsa — last_free_at eng eski bo'lganini tanla
 *   4. Assign qil, waiter → BUSY, order → ASSIGNED
 *   5. FREE waiter yo'q bo'lsa → order = WAITING_FOR_WAITER (navbat)
 *   6. Waiter DELIVERED bosganda → FREE → navbatdagi eng eski order
 *      avtomatik assign
 */
class WaiterAssignmentService
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Admin ACCEPT bosgach: ACCEPTED + biriktirish — BITTA transaction.
     *
     * Ikkisi ajralib qolsa, order ACCEPTED bo'lib qolib, hech kimga
     * biriktirilmay "osilib" turishi mumkin edi.
     */
    public function acceptAndAssign(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $this->lockForAssignment($order->restaurant_id);

            $accepted = $this->orders->changeStatus($order, OrderStatus::ACCEPTED);

            return $this->assign($accepted);
        });
    }

    /**
     * ⚠️ BIRIKTIRISH MUTEX'i — restoran qatorini qulflaydi.
     *
     * Nega kerak: `TableStatusService::recalculate()` `tables` qatoriga
     * FAQAT status o'zgarganda yozadi. Ya'ni bir transaction `tables` ni
     * qulflaydi, boshqasi (status allaqachon to'g'ri bo'lgani uchun)
     * qulflamay `users` ga o'tib ketadi — va keyin `tables` ni so'raydi.
     * Natija: qulflash tartibi teskari bo'lib DEADLOCK (1213).
     *
     * Shuning uchun biriktirishga aloqador HAR BIR transaction eng
     * avval shu qatorni oladi — bitta restoran ichida biriktirish
     * qat'iy navbat bilan ketadi.
     *
     * `DB::table()` ataylab: global scope va soft delete filtri
     * qulflashga xalaqit bermasin.
     */
    public function lockForAssignment(int $restaurantId): void
    {
        DB::table('restaurants')->where('id', $restaurantId)->lockForUpdate()->first();
    }

    /**
     * Orderni bo'sh afitsantga biriktiradi yoki navbatga qo'yadi.
     *
     * ⚠️ DRAFT hech qachon biriktirilmaydi (docs/05-PHASE0-PLAN.md §2.4).
     */
    public function assign(Order $order): Order
    {
        if ($order->status->isDraft()) {
            return $order;
        }

        return DB::transaction(function () use ($order): Order {
            $this->lockForAssignment($order->restaurant_id);

            $waiter = $this->pickWaiter($order->restaurant_id);

            if ($waiter === null) {
                // Bo'sh afitsant yo'q — navbatga. Admin panelda
                // "Barcha afitsantlar band" ko'rinadi.
                return $this->orders->changeStatus($order, OrderStatus::WAITING_FOR_WAITER);
            }

            $order->forceFill(['waiter_id' => $waiter->id])->save();

            $assigned = $this->orders->changeStatus($order, OrderStatus::ASSIGNED);

            $waiter->forceFill(['status' => WaiterStatus::BUSY])->save();

            return $assigned;
        });
    }

    /**
     * Afitsant bo'shagach: navbatdagi ENG ESKI orderni unga beradi
     * (docs/01 §7, 6-qadam).
     *
     * Bir afitsant bir vaqtda bitta orderni oladi — qolganlari
     * navbatda kutadi.
     */
    public function assignNextQueued(int $restaurantId): ?Order
    {
        return DB::transaction(function () use ($restaurantId): ?Order {
            $this->lockForAssignment($restaurantId);

            $queued = Order::query()
                ->where('restaurant_id', $restaurantId)
                ->where('status', OrderStatus::WAITING_FOR_WAITER)
                // Eng eski — kim oldin kutgan bo'lsa, o'sha oldin oladi.
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($queued === null) {
                return null;
            }

            $waiter = $this->pickWaiter($restaurantId);

            if ($waiter === null) {
                return null;
            }

            $queued->forceFill(['waiter_id' => $waiter->id])->save();
            $assigned = $this->orders->changeStatus($queued, OrderStatus::ASSIGNED);

            $waiter->forceFill(['status' => WaiterStatus::BUSY])->save();

            return $assigned;
        });
    }

    /** Navbatda nechta order kutayapti — admin paneli uchun. */
    public function queuedCount(int $restaurantId): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('status', OrderStatus::WAITING_FOR_WAITER)
            ->count();
    }

    /**
     * docs/01-ARCHITECTURE.md §7, 1-3 qadamlar.
     *
     * ⚠️ CONCURRENCY — IKKI QATLAM:
     *
     *   1. `lockForAssignment()` (yuqorida) — asosiy kafolat. Ikki admin
     *      bir vaqtda ACCEPT bossa, ikkinchisi birinchisi tugagunicha
     *      kutadi.
     *   2. Nomzodlar `lockForUpdate()` bilan olinadi — zaxira qatlam.
     *      Qulflovchi o'qish eng oxirgi commit qilingan holatni ko'radi,
     *      shuning uchun endi BUSY bo'lgan afitsant ro'yxatga TUSHMAYDI.
     *
     * Ikkalasi ham `AssignmentConcurrencyTest` da har birini alohida
     * o'chirib sinab ko'rilgan.
     */
    private function pickWaiter(int $restaurantId): ?User
    {
        $candidates = User::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('role', UserRole::WAITER)
            // OFFLINE va BUSY chetlab o'tiladi: OFFLINE afitsant
            // smenada emas, unga order bersak u yo'qoladi.
            ->where('status', WaiterStatus::FREE)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            // Deterministik tartib — deadlock ehtimolini kamaytiradi.
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Har bir nomzodning ochiq orderlari soni.
        $load = Order::withoutGlobalScopes()
            ->whereIn('waiter_id', $candidates->pluck('id'))
            ->open()
            ->selectRaw('waiter_id, COUNT(*) as total')
            ->groupBy('waiter_id')
            ->pluck('total', 'waiter_id');

        // ⚠️ `sortBy([fn, fn])` ISHLATILMAYDI: Laravel `sortByMany` ichida
        // callable'ni QIYMAT emas, KOMPARATOR deb chaqiradi ($fn($a, $b)) —
        // natijada tartib buzilgan bo'lardi. Shuning uchun ochiq komparator.
        return $candidates
            ->sort(function (User $a, User $b) use ($load): int {
                // 2-qadam: eng kam active order.
                $byLoad = ((int) ($load[$a->id] ?? 0)) <=> ((int) ($load[$b->id] ?? 0));

                if ($byLoad !== 0) {
                    return $byLoad;
                }

                // 3-qadam: teng bo'lsa — eng uzoq bo'sh turgani.
                // `last_free_at` null (hali ishlamagan) = eng eski,
                // shuning uchun u birinchi navbatda tanlanadi.
                return ($a->last_free_at?->getTimestamp() ?? 0)
                    <=> ($b->last_free_at?->getTimestamp() ?? 0);
            })
            ->first();
    }
}
