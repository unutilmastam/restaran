<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Support\RestaurantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PHASE 2 + 2.5 qabul mezoni. */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        RestaurantContext::allowCrossRestaurant();
    }

    public function test_it_seeds_three_plans_with_no_price(): void
    {
        // Narxlar KODDA yozilmaydi — super admin kiritadi (javob 1).
        $this->assertSame(3, Plan::count());

        foreach (['MONTHLY' => 30, 'QUARTERLY' => 90, 'YEARLY' => 365] as $code => $days) {
            $plan = Plan::where('code', $code)->firstOrFail();

            $this->assertSame($days, $plan->days);
            $this->assertSame(0.0, $plan->price);
            $this->assertTrue($plan->is_active);
        }
    }

    public function test_it_seeds_platform_contact_settings(): void
    {
        // docs/06-SAAS.md §12 — to'lov sahifasi shulardan o'qiydi.
        foreach (['contact_phone', 'contact_telegram', 'contact_note_ru', 'contact_note_uz'] as $key) {
            $this->assertDatabaseHas('settings', ['restaurant_id' => null, 'key' => $key]);
        }

        $this->assertSame(4, Setting::whereNull('restaurant_id')->count());
    }

    public function test_it_seeds_one_super_admin_without_a_restaurant(): void
    {
        $superAdmins = User::where('role', UserRole::SUPER_ADMIN)->get();

        $this->assertCount(1, $superAdmins);
        $this->assertNull($superAdmins->first()->restaurant_id);
    }

    public function test_it_seeds_one_restaurant_on_a_seven_day_trial(): void
    {
        $restaurant = Restaurant::where('slug', 'demo')->firstOrFail();

        $this->assertSame(SubscriptionStatus::TRIAL, $restaurant->subscription_status);
        $this->assertSame(7, $restaurant->daysLeft());
        $this->assertTrue($restaurant->isOperational());

        $this->assertDatabaseHas('subscriptions', [
            'restaurant_id' => $restaurant->id,
            'status' => SubscriptionStatus::TRIAL->value,
        ]);
    }

    public function test_it_seeds_default_limits(): void
    {
        $restaurant = Restaurant::where('slug', 'demo')->firstOrFail();

        // docs/06-SAAS.md §8 (javob 7) — 30 / 100 / 10.
        $this->assertSame(30, $restaurant->max_tables);
        $this->assertSame(100, $restaurant->max_products);
        $this->assertSame(10, $restaurant->max_waiters);
    }

    public function test_it_seeds_one_owner_admin_and_three_waiters(): void
    {
        $this->assertSame(1, User::where('role', UserRole::OWNER_ADMIN)->count());
        $this->assertSame(3, User::where('role', UserRole::WAITER)->count());

        foreach (['Hasan', 'Akmal', 'Ali'] as $name) {
            $this->assertDatabaseHas('users', ['name' => $name, 'role' => UserRole::WAITER->value]);
        }
    }

    public function test_it_seeds_twenty_tables_with_unique_random_tokens(): void
    {
        $tokens = Table::pluck('nfc_token');

        $this->assertCount(20, $tokens);
        $this->assertSame(20, $tokens->unique()->count());

        foreach ($tokens as $token) {
            // docs/01-ARCHITECTURE.md §4 — taxmin qilib bo'lmaydigan.
            $this->assertSame(64, strlen($token));
        }
    }

    public function test_the_menu_starts_empty(): void
    {
        // docs/06-SAAS.md §9 (javob 8) — yangi restoranda demo kontent
        // QO'YILMAYDI, egasi o'zi to'ldiradi.
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Product::count());
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        RestaurantContext::allowCrossRestaurant();

        $this->assertSame(3, Plan::count());
        $this->assertSame(1, Restaurant::count());
        $this->assertSame(20, Table::count());
        $this->assertSame(1, User::where('role', UserRole::OWNER_ADMIN)->count());
    }
}
