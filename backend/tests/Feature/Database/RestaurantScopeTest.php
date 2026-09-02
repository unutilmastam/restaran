<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/06-SAAS.md §10 + docs/07-DB-DECISIONS.md §2.
 *
 * SaaS'ning eng katta xavfi — bir restoran boshqasining ma'lumotini
 * ko'rishi. To'liq endpoint bo'yicha izolyatsiya testi PHASE 14 da;
 * bu yerda scope mexanizmining o'zi qulflanadi.
 */
class RestaurantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $a;

    private Restaurant $b;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();
        $this->a = Restaurant::factory()->create();
        $this->b = Restaurant::factory()->create();

        foreach ([$this->a, $this->b] as $restaurant) {
            Category::create([
                'restaurant_id' => $restaurant->id,
                'name_ru' => 'Категория',
                'name_uz' => 'Kategoriya',
                'slug' => 'cat-'.$restaurant->id,
            ]);
        }

        RestaurantContext::reset();
    }

    public function test_a_restaurant_only_sees_its_own_rows(): void
    {
        RestaurantContext::set($this->a->id);

        $this->assertSame(1, Category::count());
        $this->assertSame($this->a->id, Category::first()->restaurant_id);
    }

    public function test_without_a_resolved_restaurant_nothing_is_returned(): void
    {
        // ⚠️ Eng muhim qoida: aniqlanmagan holatda cheklovsiz QOLDIRILMAYDI.
        // Kod xatosi bo'lsa sahifa bo'sh qoladi, ma'lumot sizib chiqmaydi.
        $this->assertSame(0, Category::count());
    }

    public function test_the_scope_is_bypassed_only_when_explicitly_unscoped(): void
    {
        RestaurantContext::allowCrossRestaurant();

        $this->assertSame(2, Category::count());
    }

    public function test_a_super_admin_alone_does_not_bypass_the_scope(): void
    {
        // Rol yetarli EMAS. SUPER_ADMIN /admin/* ga kirsa ham faqat o'z
        // kontekstidagi restoranni ko'radi — chetlab o'tish middleware
        // orqali, faqat /super/* da.
        RestaurantContext::allowCrossRestaurant();
        $superAdmin = User::factory()->superAdmin()->create();
        RestaurantContext::reset();

        $this->actingAs($superAdmin);

        $this->assertSame(0, Category::count());
    }

    public function test_creating_a_row_fills_restaurant_id_from_the_context(): void
    {
        RestaurantContext::set($this->b->id);

        $category = Category::create([
            'name_ru' => 'Новая',
            'name_uz' => 'Yangi',
            'slug' => 'yangi',
        ]);

        // restaurant_id HECH QACHON request'dan olinmaydi (docs/06 §10.2).
        $this->assertSame($this->b->id, $category->restaurant_id);
    }

    public function test_settings_also_expose_platform_level_rows(): void
    {
        RestaurantContext::allowCrossRestaurant();
        Setting::create(['restaurant_id' => null, 'key' => 'contact_phone', 'value' => '+998']);
        Setting::create(['restaurant_id' => $this->a->id, 'key' => 'voice_enabled', 'value' => '1']);
        RestaurantContext::reset();

        RestaurantContext::set($this->a->id);

        // Platforma sozlamasi + o'z sozlamasi = 2. Aks holda to'lov
        // sahifasi aloqa ma'lumotisiz qolardi (docs/07 §2).
        $this->assertSame(2, Setting::count());
    }

    public function test_settings_of_another_restaurant_stay_hidden(): void
    {
        RestaurantContext::allowCrossRestaurant();
        Setting::create(['restaurant_id' => $this->b->id, 'key' => 'voice_enabled', 'value' => '1']);
        RestaurantContext::reset();

        RestaurantContext::set($this->a->id);

        $this->assertSame(0, Setting::count());
    }

    public function test_a_restaurant_admin_cannot_see_the_super_admin_account(): void
    {
        RestaurantContext::allowCrossRestaurant();
        User::factory()->superAdmin()->create();
        User::factory()->ownerAdmin()->create(['restaurant_id' => $this->a->id]);
        RestaurantContext::reset();

        RestaurantContext::set($this->a->id);

        // `User` scope'ida `orWhereNull` YO'Q — shuning uchun SUPER_ADMIN
        // ko'rinmaydi.
        $this->assertSame(1, User::count());
        $this->assertSame($this->a->id, User::first()->restaurant_id);
    }
}
