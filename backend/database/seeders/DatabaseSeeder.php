<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\RestaurantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder konsoldan yuriladi — `auth()` yo'q, shuning uchun global
        // scope hech qanday restoranni aniqlay olmaydi va `whereRaw('1 = 0')`
        // qaytaradi (docs/07-DB-DECISIONS.md §2). Seeding vaqtida scope
        // ochiladi; barcha `restaurant_id` lar ANIQ ko'rsatiladi.
        RestaurantContext::allowCrossRestaurant();

        $this->call([
            PlanSeeder::class,
            PlatformSettingSeeder::class,
            SuperAdminSeeder::class,
            DemoRestaurantSeeder::class,
        ]);

        RestaurantContext::reset();
    }
}
