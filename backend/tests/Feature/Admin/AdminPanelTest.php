<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** docs/03-PHASES.md PHASE 6. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private Table $table;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create([
            'slug' => 'demo', 'max_tables' => 2, 'max_products' => 2, 'max_waiters' => 1,
        ]);

        $this->owner = User::factory()->ownerAdmin()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'owner',
            'password' => 'password',
        ]);

        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 1, 'capacity' => 4, 'nfc_token' => str_repeat('a', 64),
        ]);

        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_ru' => 'Горячее', 'name_uz' => 'Issiq', 'slug' => 'hot',
        ]);

        RestaurantContext::reset();
    }

    private function asOwner(): static
    {
        RestaurantContext::reset();

        return $this->actingAs($this->owner, 'sanctum');
    }

    // ── 1. AUTH ────────────────────────────────────────────────────────

    public function test_an_admin_can_log_in_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner',
            'password' => 'password',
        ])->assertOk();

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(UserRole::OWNER_ADMIN->value, $response->json('data.user.role'));
        $this->assertSame($this->restaurant->name, $response->json('data.user.restaurant.name'));
    }

    public function test_the_issued_token_actually_works_on_a_protected_route(): void
    {
        /*
         * ⚠️ `actingAs()` YETARLI EMAS — u autentifikatsiyani chetlab
         * o'tadi va HAQIQIY token oqimini sinamaydi.
         *
         * Bu test aynan shu bo'shliqni yopadi: `User` modelidagi global
         * scope Sanctum tokenini yechishga to'sqinlik qilardi (kontekst
         * hali o'rnatilmagan payt), natijada har bir so'rov 401 berardi.
         * Faqat brauzerda ko'rindi.
         */
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner',
            'password' => 'password',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.username', 'owner');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'data.tables');
    }

    public function test_logging_out_invalidates_the_token(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner', 'password' => 'password',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // ⚠️ Test harness auth guard'ni bitta test ichida keshlaydi —
        // haqiqiy so'rovda bunday bo'lmaydi. Tozalamasak keyingi
        // chaqiruv eski, allaqachon o'chirilgan foydalanuvchini ko'radi.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_a_token_only_reaches_its_own_restaurant(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        Table::create([
            'restaurant_id' => $other->id, 'number' => 77,
            'capacity' => 4, 'nfc_token' => str_repeat('c', 64),
        ]);
        RestaurantContext::reset();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner', 'password' => 'password',
        ])->json('data.token');

        $numbers = collect(
            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/admin/tables')->json('data.items')
        )->pluck('number');

        $this->assertSame([1], $numbers->all());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['login' => 'owner', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    public function test_a_super_admin_cannot_use_the_restaurant_admin_panel(): void
    {
        // docs/07-DB-DECISIONS.md §2 — SUPER_ADMIN /admin/* da imtiyoz
        // olmaydi; platforma boshqaruvi /super/* da (PHASE 13.5).
        RestaurantContext::allowCrossRestaurant();
        $super = User::factory()->superAdmin()->create();
        RestaurantContext::reset();

        $this->actingAs($super, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FORBIDDEN');
    }

    public function test_a_waiter_cannot_use_the_admin_panel(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $waiter = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
        RestaurantContext::reset();

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_the_admin_panel_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    // ── 2. DASHBOARD ───────────────────────────────────────────────────

    public function test_the_dashboard_returns_todays_figures_and_the_table_grid(): void
    {
        $response = $this->asOwner()->getJson('/api/v1/admin/dashboard')->assertOk();

        foreach (['revenue', 'orders', 'guests', 'average_check', 'pending_orders'] as $key) {
            $this->assertArrayHasKey($key, $response->json('data.today'));
        }

        $this->assertCount(1, $response->json('data.tables'));
        $this->assertSame(1, $response->json('data.tables.0.number'));
        $this->assertSame('AVAILABLE', $response->json('data.tables.0.status'));
        $this->assertSame(2, $response->json('data.limits.tables.max'));
    }

    // ── 3. ORDER QABUL QILISH ──────────────────────────────────────────

    private ?TableSession $session = null;

    /** Bitta stolda faqat BITTA ochiq session bo'ladi — qayta ishlatamiz. */
    private function makeOrder(OrderStatus $status = OrderStatus::PENDING, bool $draft = false): Order
    {
        RestaurantContext::allowCrossRestaurant();

        $session = $this->session ??= TableSession::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_count' => 2,
            'status' => SessionStatus::ACTIVE,
            'public_id' => Str::random(32),
            'opened_at' => now(),
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_id' => $draft ? null : $session->id,
            'client_order_uuid' => (string) Str::uuid(),
            'order_number' => '#'.str_pad((string) (Order::withoutGlobalScopes()->count() + 1), 4, '0', STR_PAD_LEFT),
            'business_date' => now()->toDateString(),
            'status' => $draft ? OrderStatus::DRAFT : $status,
            'guest_count' => 2,
            'subtotal' => 45000, 'discount' => 0, 'total' => 45000,
        ]);

        RestaurantContext::reset();

        return $order;
    }

    public function test_an_admin_can_accept_a_pending_order(): void
    {
        $order = $this->makeOrder();

        $this->asOwner()
            ->postJson("/api/v1/admin/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::ACCEPTED->value);

        RestaurantContext::allowCrossRestaurant();
        $this->assertNotNull($order->refresh()->accepted_at);
    }

    public function test_accepting_twice_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->asOwner()->postJson("/api/v1/admin/orders/{$order->id}/accept")->assertOk();
        $this->asOwner()->postJson("/api/v1/admin/orders/{$order->id}/accept")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_drafts_never_appear_in_the_admin_order_list(): void
    {
        $this->makeOrder();
        $this->makeOrder(draft: true);

        $response = $this->asOwner()->getJson('/api/v1/admin/orders')->assertOk();

        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame(OrderStatus::PENDING->value, $response->json('data.items.0.status'));
        // Admin kartochkasida stol raqami ko'rinishi kerak.
        $this->assertSame(1, $response->json('data.items.0.table.number'));
    }

    public function test_a_draft_cannot_be_accepted_through_the_admin_endpoint(): void
    {
        $draft = $this->makeOrder(draft: true);

        $this->asOwner()->postJson("/api/v1/admin/orders/{$draft->id}/accept")->assertNotFound();
    }

    // ── 4. MENYU CRUD ──────────────────────────────────────────────────

    public function test_both_language_names_are_required_for_a_product(): void
    {
        // docs/02-I18N-RU-UZ.md §3
        $base = [
            'category_id' => $this->category->id,
            'name_uz' => 'Osh',
            'name_ru' => 'Плов',
            'price' => 45000,
        ];

        $this->asOwner()->postJson('/api/v1/admin/products', $base)->assertCreated();

        $this->asOwner()->postJson('/api/v1/admin/products', ['name_ru' => null] + $base)
            ->assertStatus(422)
            ->assertJsonPath('data.fields.name_ru.0', 'Nomi (RU) to\'ldirilishi shart.');

        $this->asOwner()->postJson('/api/v1/admin/products', ['name_uz' => null] + $base)
            ->assertStatus(422);
    }

    public function test_both_language_names_are_required_for_a_category(): void
    {
        $this->asOwner()->postJson('/api/v1/admin/categories', [
            'name_uz' => 'Salatlar', 'slug' => 'salads',
        ])->assertStatus(422);

        $this->asOwner()->postJson('/api/v1/admin/categories', [
            'name_uz' => 'Salatlar', 'name_ru' => 'Салаты', 'slug' => 'salads',
        ])->assertCreated();
    }

    public function test_a_category_from_another_restaurant_cannot_be_used(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        $foreign = Category::create([
            'restaurant_id' => $other->id, 'name_ru' => 'X', 'name_uz' => 'X', 'slug' => 'x',
        ]);
        RestaurantContext::reset();

        $this->asOwner()->postJson('/api/v1/admin/products', [
            'category_id' => $foreign->id,
            'name_uz' => 'Osh', 'name_ru' => 'Плов', 'price' => 1000,
        ])->assertStatus(422);
    }

    // ── 5. RASM ────────────────────────────────────────────────────────

    public function test_an_uploaded_image_is_converted_to_webp_under_the_restaurant_folder(): void
    {
        Storage::fake('public');

        $product = $this->asOwner()->postJson('/api/v1/admin/products', [
            'category_id' => $this->category->id,
            'name_uz' => 'Osh', 'name_ru' => 'Плов', 'price' => 45000,
        ])->json('data.product');

        $response = $this->asOwner()->post("/api/v1/admin/products/{$product['id']}/image", [
            'image' => UploadedFile::fake()->image('photo.jpg', 2000, 1500),
        ])->assertOk();

        $path = $response->json('data.product.image');

        $this->assertStringStartsWith("products/{$this->restaurant->id}/", $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        // Rasm 800px gacha kichraytirilgan (1 GB disk byudjeti).
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(800, $size[0]);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $product = $this->asOwner()->postJson('/api/v1/admin/products', [
            'category_id' => $this->category->id,
            'name_uz' => 'Osh', 'name_ru' => 'Плов', 'price' => 45000,
        ])->json('data.product');

        // `.png` deb nomlangan, lekin mazmuni PHP — MIME fayl
        // MAZMUNIDAN aniqlanadi.
        $this->asOwner()->post("/api/v1/admin/products/{$product['id']}/image", [
            'image' => UploadedFile::fake()->createWithContent('evil.png', '<?php echo 1;'),
        ])->assertStatus(422);
    }

    // ── 6. STOL CRUD ───────────────────────────────────────────────────

    public function test_creating_a_table_generates_a_token_and_an_nfc_url(): void
    {
        $response = $this->asOwner()->postJson('/api/v1/admin/tables', [
            'number' => 5, 'capacity' => 6,
        ])->assertCreated();

        $token = $response->json('data.table.nfc_token');

        $this->assertSame(64, strlen($token));
        $this->assertStringContainsString("/r/demo/t/{$token}", $response->json('data.table.nfc_url'));
    }

    public function test_a_client_supplied_token_is_ignored(): void
    {
        // docs/01-ARCHITECTURE.md §4 — token har doim serverda.
        $response = $this->asOwner()->postJson('/api/v1/admin/tables', [
            'number' => 6, 'capacity' => 4, 'nfc_token' => 'taxmin-qilingan-token',
        ])->assertCreated();

        $this->assertNotSame('taxmin-qilingan-token', $response->json('data.table.nfc_token'));
    }

    public function test_regenerating_the_token_invalidates_the_old_one(): void
    {
        $old = $this->table->nfc_token;

        $this->asOwner()
            ->postJson("/api/v1/admin/tables/{$this->table->id}/regenerate-token")
            ->assertOk();

        $this->assertNotSame($old, $this->table->refresh()->nfc_token);
        // Eski tag endi ishlamaydi.
        $this->getJson("/api/v1/t/{$old}")->assertNotFound();
    }

    // ── 7-8. XODIMLAR ──────────────────────────────────────────────────

    public function test_an_admin_can_add_another_admin(): void
    {
        // docs/06-SAAS.md §1 (javob 4)
        $this->asOwner()->postJson('/api/v1/admin/staff', [
            'name' => 'Ikkinchi admin', 'username' => 'admin2',
            'password' => 'password', 'role' => UserRole::ADMIN->value,
        ])->assertCreated()->assertJsonPath('data.staff.role', UserRole::ADMIN->value);
    }

    public function test_nobody_can_promote_themselves_to_owner_admin(): void
    {
        $this->asOwner()->postJson('/api/v1/admin/staff', [
            'name' => 'Egallovchi', 'username' => 'usurper',
            'password' => 'password', 'role' => UserRole::OWNER_ADMIN->value,
        ])->assertStatus(422);
    }

    public function test_the_owner_admin_cannot_be_deleted(): void
    {
        $this->asOwner()
            ->deleteJson("/api/v1/admin/staff/{$this->owner->id}")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'OWNER_ADMIN_PROTECTED');

        RestaurantContext::allowCrossRestaurant();
        $this->assertNotNull(User::find($this->owner->id));
    }

    public function test_the_owner_admin_cannot_be_demoted(): void
    {
        $this->asOwner()->putJson("/api/v1/admin/staff/{$this->owner->id}", [
            'name' => $this->owner->name, 'username' => $this->owner->username,
            'role' => UserRole::WAITER->value,
        ])->assertForbidden()->assertJsonPath('error_code', 'OWNER_ADMIN_PROTECTED');
    }

    public function test_a_waiter_can_be_deleted(): void
    {
        $waiter = $this->asOwner()->postJson('/api/v1/admin/staff', [
            'name' => 'Hasan', 'username' => 'hasan',
            'password' => 'password', 'role' => UserRole::WAITER->value,
        ])->json('data.staff');

        $this->assertTrue($waiter['is_deletable']);
        $this->asOwner()->deleteJson("/api/v1/admin/staff/{$waiter['id']}")->assertOk();
    }

    // ── 9. LIMITLAR ────────────────────────────────────────────────────

    public function test_the_table_limit_is_enforced(): void
    {
        // max_tables = 2, bittasi allaqachon bor.
        $this->asOwner()->postJson('/api/v1/admin/tables', ['number' => 2, 'capacity' => 4])
            ->assertCreated();

        $this->asOwner()->postJson('/api/v1/admin/tables', ['number' => 3, 'capacity' => 4])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'LIMIT_EXCEEDED')
            ->assertJsonPath('data.max', 2);
    }

    public function test_the_product_limit_is_enforced(): void
    {
        $payload = [
            'category_id' => $this->category->id,
            'name_uz' => 'Osh', 'name_ru' => 'Плов', 'price' => 1000,
        ];

        $this->asOwner()->postJson('/api/v1/admin/products', $payload)->assertCreated();
        $this->asOwner()->postJson('/api/v1/admin/products', $payload)->assertCreated();

        $this->asOwner()->postJson('/api/v1/admin/products', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'LIMIT_EXCEEDED');
    }

    public function test_the_waiter_limit_is_enforced(): void
    {
        $payload = fn (string $username): array => [
            'name' => 'Waiter', 'username' => $username,
            'password' => 'password', 'role' => UserRole::WAITER->value,
        ];

        $this->asOwner()->postJson('/api/v1/admin/staff', $payload('w1'))->assertCreated();

        // max_waiters = 1
        $this->asOwner()->postJson('/api/v1/admin/staff', $payload('w2'))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'LIMIT_EXCEEDED');

        // ADMIN limitga kirmaydi.
        $this->asOwner()->postJson('/api/v1/admin/staff', [
            'name' => 'Admin', 'username' => 'a2',
            'password' => 'password', 'role' => UserRole::ADMIN->value,
        ])->assertCreated();
    }

    // ── MULTI-TENANT ───────────────────────────────────────────────────

    public function test_an_admin_never_sees_another_restaurants_data(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        Table::create([
            'restaurant_id' => $other->id, 'number' => 99,
            'capacity' => 4, 'nfc_token' => str_repeat('b', 64),
        ]);
        $foreignCategory = Category::create([
            'restaurant_id' => $other->id, 'name_ru' => 'X', 'name_uz' => 'X', 'slug' => 'x',
        ]);
        Product::create([
            'restaurant_id' => $other->id, 'category_id' => $foreignCategory->id,
            'name_ru' => 'Чужое', 'name_uz' => 'Begona', 'price' => 1000,
        ]);
        User::factory()->create(['restaurant_id' => $other->id]);
        RestaurantContext::reset();

        $this->assertCount(1, $this->asOwner()->getJson('/api/v1/admin/tables')->json('data.items'));
        $this->assertCount(0, $this->asOwner()->getJson('/api/v1/admin/products')->json('data.items'));
        $this->assertCount(1, $this->asOwner()->getJson('/api/v1/admin/categories')->json('data.items'));
        $this->assertCount(1, $this->asOwner()->getJson('/api/v1/admin/staff')->json('data.items'));
    }
}
