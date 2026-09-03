<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Afitsant oqimi — docs/03-PHASES.md PHASE 7.
 *
 * Waiter statusi (`FREE`/`BUSY`) ORDER holatidan kelib chiqadi:
 * afitsantda ochiq order bo'lsa BUSY, bo'lmasa FREE. Uni qo'lda
 * BUSY qilib bo'lmaydi — buni tizim hisoblaydi (docs/01 §3).
 */
class WaiterService
{
    public function __construct(private readonly OrderService $orders) {}

    /** Menga biriktirilgan, hali yakunlanmagan orderlar. */
    public function activeOrders(User $waiter): Collection
    {
        return Order::query()
            ->where('waiter_id', $waiter->id)
            ->open()
            ->with(['items', 'table:id,number,name'])
            ->orderBy('assigned_at')
            ->get();
    }

    public function history(User $waiter, int $days = 7): Collection
    {
        return Order::query()
            ->where('waiter_id', $waiter->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::CANCELLED->value])
            ->where('created_at', '>=', now()->subDays($days))
            ->with(['items', 'table:id,number,name'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** ASSIGNED → WAITER_ACCEPTED. Afitsant BAND bo'ladi. */
    public function accept(User $waiter, Order $order): Order
    {
        $this->guard($waiter, $order);

        return DB::transaction(function () use ($waiter, $order): Order {
            $updated = $this->orders->changeStatus($order, OrderStatus::WAITER_ACCEPTED);
            $this->syncStatus($waiter);

            return $updated;
        });
    }

    /** WAITER_ACCEPTED → DELIVERING. Afitsant oshxonadan olib ketdi. */
    public function startDelivering(User $waiter, Order $order): Order
    {
        $this->guard($waiter, $order);

        return $this->orders->changeStatus($order, OrderStatus::DELIVERING);
    }

    /**
     * DELIVERING → DELIVERED. Afitsant BO'SH bo'ladi.
     *
     * ⚠️ Bu tugma faqat mijozga YETKAZGANDAN KEYIN bosiladi — shuning
     * uchun UI da u alohida tasdiqlash bilan himoyalangan.
     */
    public function deliver(User $waiter, Order $order): Order
    {
        $this->guard($waiter, $order);

        return DB::transaction(function () use ($waiter, $order): Order {
            $updated = $this->orders->changeStatus($order, OrderStatus::DELIVERED);
            $this->syncStatus($waiter);

            return $updated;
        });
    }

    /**
     * Afitsant o'zini FREE yoki OFFLINE qila oladi.
     *
     * ⚠️ `BUSY` ni QO'LDA qo'yib bo'lmaydi — u ochiq orderdan kelib
     * chiqadi (docs/01-ARCHITECTURE.md §3).
     */
    public function setAvailability(User $waiter, WaiterStatus $status): User
    {
        if ($status === WaiterStatus::BUSY) {
            throw new BusinessException('INVALID_STATUS_TRANSITION', 422);
        }

        // Ochiq orderi bor afitsant o'zini bo'sh deb e'lon qila olmaydi.
        if ($status === WaiterStatus::FREE && $this->openOrderCount($waiter) > 0) {
            throw new BusinessException('ORDER_NOT_DELIVERED', 409);
        }

        $waiter->forceFill([
            'status' => $status,
            'last_free_at' => $status === WaiterStatus::FREE ? now() : $waiter->last_free_at,
        ])->save();

        return $waiter->refresh();
    }

    /**
     * Statusni ochiq orderlar soniga qarab yangilaydi.
     *
     * `last_free_at` — WaiterAssignmentService uchun (docs/01 §7):
     * teng yukda eng uzoq bo'sh turgan afitsant tanlanadi.
     */
    public function syncStatus(User $waiter): void
    {
        if ($waiter->status === WaiterStatus::OFFLINE) {
            return;
        }

        $busy = $this->openOrderCount($waiter) > 0;

        $waiter->forceFill([
            'status' => $busy ? WaiterStatus::BUSY : WaiterStatus::FREE,
            'last_free_at' => $busy ? $waiter->last_free_at : now(),
        ])->save();
    }

    private function openOrderCount(User $waiter): int
    {
        return Order::query()->where('waiter_id', $waiter->id)->open()->count();
    }

    /**
     * ⚠️ Afitsant BOSHQA afitsantning orderiga tega olmaydi
     * (docs/04-TEST-SCENARIO.md "Waiter izolyatsiyasi").
     */
    private function guard(User $waiter, Order $order): void
    {
        if ($waiter->role !== UserRole::WAITER || $order->waiter_id !== $waiter->id) {
            throw new BusinessException('FORBIDDEN', 403);
        }
    }
}
