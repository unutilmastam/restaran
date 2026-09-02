<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SessionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Support\Carbon;

/**
 * Admin dashboard ko'rsatkichlari — docs/03-PHASES.md PHASE 6.
 *
 * ⚠️ "Bugun" chegarasi RESTORAN timezone'ida hisoblanadi, UTC'da emas:
 * Toshkentda soat 02:00 dagi buyurtma o'sha kunga tegishli.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function today(Restaurant $restaurant): array
    {
        [$from, $to] = $this->dayBounds($restaurant);

        // Daromad — TO'LANGAN to'lovlar (docs/01-ARCHITECTURE.md §14).
        $revenue = Payment::query()
            ->where('status', PaymentStatus::PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $orders = Order::query()
            ->visible()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $guests = (int) TableSession::query()
            ->whereBetween('opened_at', [$from, $to])
            ->sum('guest_count');

        $closedSessions = TableSession::query()
            ->whereIn('status', [SessionStatus::PAID->value, SessionStatus::CLOSED->value])
            ->whereBetween('opened_at', [$from, $to])
            ->count();

        return [
            'revenue' => round((float) $revenue, 2),
            'orders' => $orders,
            'guests' => $guests,
            // O'rtacha chek = daromad / yopilgan sessionlar.
            'average_check' => $closedSessions > 0
                ? round((float) $revenue / $closedSessions, 2)
                : 0.0,
            'pending_orders' => Order::query()
                ->where('status', OrderStatus::PENDING)
                ->count(),
        ];
    }

    /**
     * Stollar grid — rangli status uchun.
     *
     * @return list<array<string, mixed>>
     */
    public function tables(): array
    {
        return Table::query()
            ->where('is_active', true)
            ->with(['activeSession' => fn ($query) => $query->withCount([
                'orders as open_orders_count' => fn ($orders) => $orders->open(),
            ])])
            ->orderBy('number')
            ->get()
            ->map(fn (Table $table): array => [
                'id' => $table->id,
                'number' => $table->number,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'status' => $table->status,
                'session' => $table->activeSession === null ? null : [
                    'public_id' => $table->activeSession->public_id,
                    'guest_count' => $table->activeSession->guest_count,
                    'total_amount' => $table->activeSession->total_amount,
                    'open_orders' => $table->activeSession->open_orders_count,
                    'opened_at' => $table->activeSession->opened_at?->toIso8601String(),
                ],
            ])
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dayBounds(Restaurant $restaurant): array
    {
        $timezone = $restaurant->timezone ?: 'UTC';

        return [
            now()->timezone($timezone)->startOfDay()->utc(),
            now()->timezone($timezone)->endOfDay()->utc(),
        ];
    }
}
