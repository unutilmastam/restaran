<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** docs/03-PHASES.md PHASE 3 — NFC kirish va menyu. */
class MenuEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create(['slug' => 'osiyo', 'name' => 'Osiyo']);
        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 2,
            'capacity' => 4,
            'nfc_token' => str_repeat('a', 64),
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_ru' => 'Горячие блюда',
            'name_uz' => 'Issiq taomlar',
            'slug' => 'hot',
        ]);

        Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_ru' => 'Плов',
            'name_uz' => 'Osh',
            'description_ru' => 'Говядина, рис',
            'description_uz' => 'Mol go\'shti, guruch',
            'price' => 45000,
        ]);

        Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'name_ru' => 'Рыба',
            'name_uz' => 'Baliq',
            'price' => 65000,
            'is_available' => false,
        ]);

        RestaurantContext::reset();
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/t/{$this->table->nfc_token}{$suffix}";
    }

    public function test_scanning_the_tag_returns_the_table_and_restaurant(): void
    {
        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.table.number', 2)
            ->assertJsonPath('data.restaurant.name', 'Osiyo')
            ->assertJsonPath('data.is_available', true);
    }

    public function test_the_table_response_never_leaks_internal_identifiers(): void
    {
        // docs/01-ARCHITECTURE.md §13
        $data = $this->getJson($this->url())->json('data');

        foreach (['id', 'restaurant_id', 'nfc_token', 'nfc_uid'] as $key) {
            $this->assertArrayNotHasKey($key, $data['table']);
        }

        $this->assertArrayNotHasKey('id', $data['restaurant']);
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->getJson('/api/v1/t/'.str_repeat('z', 64))
            ->assertNotFound()
            ->assertJsonPath('error_code', 'INVALID_TABLE');
    }

    public function test_the_slug_url_works_and_a_wrong_slug_is_rejected(): void
    {
        $this->getJson("/api/v1/r/osiyo/t/{$this->table->nfc_token}")
            ->assertOk()
            ->assertJsonPath('data.restaurant.slug', 'osiyo');

        $this->getJson("/api/v1/r/boshqa/t/{$this->table->nfc_token}")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'INVALID_TABLE');
    }

    public function test_the_menu_is_returned_in_uzbek_by_default(): void
    {
        $response = $this->getJson($this->url('/menu'))->assertOk();

        $this->assertSame('Issiq taomlar', $response->json('data.categories.0.name'));
        $this->assertSame('Osh', $response->json('data.products.0.name'));
        $this->assertSame('Mol go\'shti, guruch', $response->json('data.products.0.description'));
    }

    public function test_the_menu_switches_to_russian_via_the_header(): void
    {
        $response = $this->withHeader('Accept-Language', 'ru')
            ->getJson($this->url('/menu'))->assertOk();

        $this->assertSame('Горячие блюда', $response->json('data.categories.0.name'));
        $this->assertSame('Плов', $response->json('data.products.0.name'));
    }

    public function test_prices_are_json_numbers_not_strings(): void
    {
        // docs/07-DB-DECISIONS.md §5
        $price = $this->getJson($this->url('/menu'))->json('data.products.0.price');

        $this->assertIsNotString($price);
        $this->assertSame(45000, $price);
    }

    public function test_unavailable_products_are_listed_but_flagged(): void
    {
        $products = collect($this->getJson($this->url('/menu'))->json('data.products'));

        $this->assertCount(2, $products);
        $this->assertFalse($products->firstWhere('name', 'Baliq')['is_available']);
    }

    public function test_search_matches_either_language(): void
    {
        $this->assertCount(1, $this->getJson($this->url('/menu?q=osh'))->json('data.products'));
        // UZ interfeysda ham ruscha qidiruv ishlaydi.
        $this->assertCount(1, $this->getJson($this->url('/menu?q=Плов'))->json('data.products'));
        $this->assertCount(0, $this->getJson($this->url('/menu?q=pizza'))->json('data.products'));
    }

    public function test_an_expired_restaurant_shows_a_message_and_hides_the_menu(): void
    {
        // docs/06-SAAS.md §4
        RestaurantContext::allowCrossRestaurant();
        $this->restaurant->update([
            'subscription_status' => SubscriptionStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
        RestaurantContext::reset();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.blocked_reason', 'RESTAURANT_UNAVAILABLE');

        $this->getJson($this->url('/menu'))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'RESTAURANT_UNAVAILABLE')
            ->assertJsonPath('message_uz', 'Restoran vaqtincha ishlamayapti');
    }

    public function test_an_archived_restaurant_looks_like_an_unknown_table(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $this->restaurant->delete();
        RestaurantContext::reset();

        $this->getJson($this->url())
            ->assertNotFound()
            ->assertJsonPath('error_code', 'INVALID_TABLE');
    }

    public function test_a_table_from_another_restaurant_is_not_reachable_through_this_menu(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        $otherCategory = Category::create([
            'restaurant_id' => $other->id,
            'name_ru' => 'Чужое', 'name_uz' => 'Begona', 'slug' => 'other',
        ]);
        Product::create([
            'restaurant_id' => $other->id,
            'category_id' => $otherCategory->id,
            'name_ru' => 'Чужой', 'name_uz' => 'Begona taom', 'price' => 1000,
        ]);
        RestaurantContext::reset();

        $names = collect($this->getJson($this->url('/menu'))->json('data.products'))
            ->pluck('name');

        $this->assertNotContains('Begona taom', $names);
        $this->assertCount(2, $names);
    }

    public function test_products_are_grouped_by_category_order(): void
    {
        // `products.sort_order` har kategoriya ichida 1 dan boshlanadi,
        // shuning uchun kategoriya tartibi birinchi bo'lishi kerak —
        // aks holda "hammasi" ko'rinishida taomlar aralashib ketadi.
        RestaurantContext::allowCrossRestaurant();

        $drinks = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_ru' => 'Напитки', 'name_uz' => 'Ichimliklar',
            'slug' => 'drinks', 'sort_order' => 2,
        ]);
        Category::where('slug', 'hot')->update(['sort_order' => 1]);

        Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $drinks->id,
            'name_ru' => 'Чай', 'name_uz' => 'Choy',
            'price' => 8000, 'sort_order' => 1,
        ]);
        RestaurantContext::reset();

        $names = collect($this->getJson($this->url('/menu'))->json('data.products'))
            ->pluck('name')
            ->all();

        // Issiq taomlar (sort_order 1) ichimliklardan (2) OLDIN keladi.
        $this->assertSame(['Osh', 'Baliq', 'Choy'], $names);
    }

    public function test_empty_categories_are_hidden(): void
    {
        RestaurantContext::allowCrossRestaurant();
        Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_ru' => 'Пустая', 'name_uz' => 'Bo\'sh', 'slug' => 'empty',
        ]);
        RestaurantContext::reset();

        $this->assertCount(1, $this->getJson($this->url('/menu'))->json('data.categories'));
    }
}
