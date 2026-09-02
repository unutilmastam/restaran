<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SubscriptionStatus;
use App\Enums\TableStatus;
use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local development uchun test restorani.
 *
 * ⚠️ MENYU BO'SH (docs/06-SAAS.md §9, javob 8): yangi restoran demo
 * kategoriya/mahsulotsiz boshlanadi — egasi o'zi to'ldiradi. Shu sababli
 * bu yerda ham kategoriya va mahsulot yaratilmaydi.
 *
 * docs/03-PHASES.md PHASE 2 dagi "5 kategoriya + 25 mahsulot" seeder
 * alohida `MenuDemoSeeder` sifatida qoladi va faqat qo'lda chaqiriladi.
 */
class DemoRestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Restoran',
                'phone' => '+998 90 000 00 00',
                'currency' => 'UZS',
                'default_locale' => 'uz',
                'timezone' => 'Asia/Tashkent',
                'is_active' => true,

                // docs/06-SAAS.md §3 (javob 2) — 7 kunlik trial.
                'subscription_status' => SubscriptionStatus::TRIAL,
                'expires_at' => now()->addDays(config('smart_restaurant.saas.trial_days')),

                // docs/06-SAAS.md §8 (javob 7) — limit restoranga biriktiriladi.
                'max_tables' => config('smart_restaurant.saas.default_limits.max_tables'),
                'max_products' => config('smart_restaurant.saas.default_limits.max_products'),
                'max_waiters' => config('smart_restaurant.saas.default_limits.max_waiters'),
            ],
        );

        Subscription::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'status' => SubscriptionStatus::TRIAL->value],
            [
                'started_at' => now(),
                'expires_at' => $restaurant->expires_at,
                'amount' => 0,
                'note' => 'Avtomatik trial (7 kun)',
            ],
        );

        // docs/06-SAAS.md §1 — birinchi admin OWNER_ADMIN. Uni o'chirib
        // bo'lmaydi; DB generated column ikkinchisini ham to'sadi.
        $this->user($restaurant->id, 'admin', 'Restoran Egasi', UserRole::OWNER_ADMIN);

        foreach (['Hasan', 'Akmal', 'Ali'] as $index => $name) {
            $this->user(
                $restaurant->id,
                'waiter'.($index + 1),
                $name,
                UserRole::WAITER,
                WaiterStatus::OFFLINE,
            );
        }

        for ($number = 1; $number <= 20; $number++) {
            Table::withoutGlobalScopes()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'number' => $number],
                [
                    // docs/01-ARCHITECTURE.md §4 — random, taxmin qilib
                    // bo'lmaydigan. URL'da table_id emas, shu ishlatiladi.
                    'nfc_token' => Str::random(64),
                    'capacity' => 4,
                    'status' => TableStatus::AVAILABLE,
                    'is_active' => true,
                ],
            );
        }
    }

    private function user(
        int $restaurantId,
        string $username,
        string $name,
        UserRole $role,
        ?WaiterStatus $status = null,
    ): void {
        User::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId, 'username' => $username],
            [
                'name' => $name,
                // ⚠️ Faqat local development uchun.
                'password' => 'password',
                'role' => $role,
                'status' => $status,
                'last_free_at' => $status === WaiterStatus::FREE ? now() : null,
                'locale' => 'uz',
                'is_active' => true,
            ],
        );
    }
}
