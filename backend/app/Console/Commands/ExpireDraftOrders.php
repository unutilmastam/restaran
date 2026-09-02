<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Muddati o'tgan DRAFT orderlarni EXPIRED qiladi.
 *
 * Bo'lmasa: mijoz 2 soat oldin cart tayyorlab ketgan bo'lsa, to'lovdan
 * keyin o'sha eski buyurtma "tirilib" yangi sessionga tushib qolardi
 * (docs/05-PHASE0-PLAN.md §2.4).
 *
 * cPanel cron `schedule:run` orqali har 10 daqiqada yuriladi.
 */
class ExpireDraftOrders extends Command
{
    protected $signature = 'orders:expire-drafts';

    protected $description = 'Muddati o\'tgan draft orderlarni EXPIRED qiladi';

    public function handle(): int
    {
        $expired = Order::withoutGlobalScopes()
            ->where('status', OrderStatus::DRAFT->value)
            ->whereNotNull('draft_expires_at')
            ->where('draft_expires_at', '<', now())
            ->update([
                'status' => OrderStatus::EXPIRED->value,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        $this->info("{$expired} ta draft order EXPIRED qilindi.");

        return self::SUCCESS;
    }
}
