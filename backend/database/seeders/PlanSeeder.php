<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * docs/06-SAAS.md §2 (javob 1).
 *
 * Narx 0 — SUPER_ADMIN panelidan kiritiladi. Narxlar KODDA yozilmaydi,
 * shuning uchun bu yerda ham haqiqiy summa yo'q.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'MONTHLY', 'name_ru' => '1 месяц', 'name_uz' => '1 oylik', 'days' => 30, 'sort_order' => 1],
            ['code' => 'QUARTERLY', 'name_ru' => '3 месяца', 'name_uz' => '3 oylik', 'days' => 90, 'sort_order' => 2],
            ['code' => 'YEARLY', 'name_ru' => '1 год', 'name_uz' => '1 yillik', 'days' => 365, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                $plan + ['price' => 0, 'is_active' => true],
            );
        }
    }
}
