<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\OrderService;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** docs/01-ARCHITECTURE.md §8 + docs/03-PHASES.md PHASE 5. */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Table $table;

    private Product $osh;

    private Product $chegirmali;

    private Product $tugagan;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create(['slug' => 'demo']);
        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 2, 'capacity' => 4, 'nfc_token' => str_repeat('a', 64),
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name_ru' => 'Горячее', 'name_uz' => 'Issiq', 'slug' => 'hot',
        ]);

        $this->osh = Product::create([
            'restaurant_id' => $this->restaurant->id, 'category_id' => $category->id,
            'name_ru' => 'Плов', 'name_uz' => 'Osh', 'price' => 45000,
        ]);

        // 10% chegirma → 28 000 dan 25 200 ga.
        $this->chegirmali = Product::create([
            'restaurant_id' => $this->restaurant->id, 'category_id' => $category->id,
            'name_ru' => 'Наполеон', 'name_uz' => 'Napoleon', 'price' => 28000, 'discount' => 10,
        ]);

        $this->tugagan = Product::create([
            'restaurant_id' => $this->restaurant->id, 'category_id' => $category->id,
            'name_ru' => 'Рыба', 'name_uz' => 'Baliq', 'price' => 65000, 'is_available' => false,
        ]);

        RestaurantContext::reset();

        $this->token = $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", [
            'guest_count' => 3,
        ])->json('data.customer_token');
    }

    /** @param  list<array<string, mixed>>  $items */
    private function submit(array $items, ?string $uuid = null, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/t/{$this->table->nfc_token}/orders", [
            'client_order_uuid' => $uuid ?? (string) Str::uuid(),
            'items' => $items,
        ] + $extra);
    }

    // ── 1. NARX ────────────────────────────────────────────────────────

    public function test_the_price_sent_by_the_frontend_is_completely_ignored(): void
    {
        // CLAUDE.md §2.6 — eng muhim xavfsizlik qoidasi.
        $response = $this->submit([
            ['product_id' => $this->osh->id, 'quantity' => 2, 'price' => 1, 'subtotal' => 2],
        ], null, ['total' => 2, 'subtotal' => 2, 'discount' => 99999]);

        $response->assertCreated();

        // DB narxi: 45 000 × 2 = 90 000. Frontend "2" yuborgan edi.
        $this->assertSame(90000, $response->json('data.order.total'));
        $this->assertSame(90000, $response->json('data.order.subtotal'));
        $this->assertSame(0, $response->json('data.order.discount'));
        $this->assertSame(45000, $response->json('data.order.items.0.price'));
    }

    public function test_the_discount_percentage_is_converted_to_an_amount_on_the_server(): void
    {
        // products.discount = FOIZ (10) → orders.discount = SUMMA (javob 6).
        $response = $this->submit([
            ['product_id' => $this->chegirmali->id, 'quantity' => 2],
        ])->assertCreated();

        // 28 000 × 2 = 56 000 subtotal; chegirma 10% = 5 600; total 50 400.
        $this->assertSame(56000, $response->json('data.order.subtotal'));
        $this->assertSame(5600, $response->json('data.order.discount'));
        $this->assertSame(50400, $response->json('data.order.total'));
        // Item snapshot'ida chegirma qo'llangan narx.
        $this->assertSame(25200, $response->json('data.order.items.0.price'));
    }

    public function test_an_unavailable_product_is_rejected(): void
    {
        $this->submit([['product_id' => $this->tugagan->id, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PRODUCT_UNAVAILABLE');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_product_from_another_restaurant_is_rejected(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        $otherCategory = Category::create([
            'restaurant_id' => $other->id, 'name_ru' => 'X', 'name_uz' => 'X', 'slug' => 'x',
        ]);
        $foreign = Product::create([
            'restaurant_id' => $other->id, 'category_id' => $otherCategory->id,
            'name_ru' => 'Чужое', 'name_uz' => 'Begona', 'price' => 1000,
        ]);
        RestaurantContext::reset();

        $this->submit([['product_id' => $foreign->id, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PRODUCT_UNAVAILABLE');
    }

    // ── 2. IDEMPOTENCY ─────────────────────────────────────────────────

    public function test_the_same_uuid_returns_the_same_order_with_200(): void
    {
        $uuid = (string) Str::uuid();

        $first = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]], $uuid)
            ->assertCreated();

        $second = $this->submit([['product_id' => $this->osh->id, 'quantity' => 5]], $uuid)
            ->assertOk();

        $this->assertSame($first->json('data.order.id'), $second->json('data.order.id'));
        // Ikkinchi so'rovdagi boshqa miqdor E'TIBORGA OLINMAYDI.
        $this->assertSame(1, $second->json('data.order.items.0.quantity'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_a_malformed_uuid_is_rejected(): void
    {
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]], 'not-a-uuid')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }

    // ── 3. DRAFT ───────────────────────────────────────────────────────

    private function makeWaitingPayment(): void
    {
        RestaurantContext::allowCrossRestaurant();
        TableSession::query()->update(['status' => SessionStatus::WAITING_PAYMENT]);
        RestaurantContext::reset();
    }

    public function test_ordering_on_a_waiting_payment_table_is_stored_as_a_draft(): void
    {
        // docs/01-ARCHITECTURE.md §12, 18-qadam.
        $this->makeWaitingPayment();

        $response = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'SESSION_WAITING_PAYMENT');

        $this->assertNotNull($response->json('data.draft_order_id'));

        RestaurantContext::allowCrossRestaurant();
        $draft = Order::firstOrFail();

        $this->assertSame(OrderStatus::DRAFT, $draft->status);
        $this->assertNull($draft->session_id);
        $this->assertNotNull($draft->draft_expires_at);
        // TTL 30 daqiqa.
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($draft->draft_expires_at), 1);
    }

    public function test_a_draft_is_hidden_from_the_admin_order_list(): void
    {
        $this->makeWaitingPayment();
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]]);

        RestaurantContext::set($this->restaurant->id);

        $this->assertSame(1, Order::query()->count());
        // Admin ro'yxati `visible()` scope'ini ishlatadi (PHASE 6).
        $this->assertSame(0, Order::query()->visible()->count());
    }

    public function test_a_draft_is_never_assignable_to_a_waiter(): void
    {
        $this->makeWaitingPayment();
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]]);

        RestaurantContext::set($this->restaurant->id);

        // `assignable()` scope'ini WaiterAssignmentService ishlatadi (PHASE 8).
        $this->assertSame(0, Order::query()->assignable()->count());
    }

    public function test_a_draft_does_not_trigger_the_order_lock(): void
    {
        $this->makeWaitingPayment();
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])->assertStatus(409);

        // Ikkinchi draft ham 409 SESSION_WAITING_PAYMENT beradi —
        // ORDER_NOT_DELIVERED EMAS. Ya'ni draft blokka kirmaydi.
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 2]])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'SESSION_WAITING_PAYMENT');

        RestaurantContext::set($this->restaurant->id);
        $this->assertSame(2, Order::query()->count());
    }

    public function test_expired_drafts_are_swept_by_the_scheduled_command(): void
    {
        $this->makeWaitingPayment();
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]]);

        RestaurantContext::allowCrossRestaurant();
        Order::query()->update(['draft_expires_at' => now()->subMinute()]);

        $this->artisan('orders:expire-drafts')->assertSuccessful();

        $this->assertSame(OrderStatus::EXPIRED, Order::firstOrFail()->status);
    }

    // ── 4. ORDER LOCK ──────────────────────────────────────────────────

    public function test_a_second_order_is_blocked_until_the_first_is_delivered(): void
    {
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])->assertCreated();

        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ORDER_NOT_DELIVERED')
            ->assertJsonPath('message_uz', 'Avvalgi buyurtmangiz hali yetkazilmagan');
    }

    public function test_a_new_order_is_allowed_once_the_previous_one_is_delivered(): void
    {
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])->assertCreated();

        RestaurantContext::allowCrossRestaurant();
        $order = Order::firstOrFail();
        $service = app(OrderService::class);

        foreach ([
            OrderStatus::ACCEPTED, OrderStatus::ASSIGNED,
            OrderStatus::WAITER_ACCEPTED, OrderStatus::DELIVERING, OrderStatus::DELIVERED,
        ] as $status) {
            $order = $service->changeStatus($order, $status);
        }
        RestaurantContext::reset();

        // docs/04-TEST-SCENARIO.md 10-qadam: yangi order ESKI session ichida.
        $second = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->assertCreated();

        $this->assertSame($order->session_id, Order::withoutGlobalScopes()
            ->find($second->json('data.order.id'))->session_id);
    }

    // ── 6. STATUS TRANSITION ───────────────────────────────────────────

    public function test_skipping_statuses_is_rejected(): void
    {
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])->assertCreated();

        RestaurantContext::allowCrossRestaurant();
        $order = Order::firstOrFail();

        $this->expectException(\App\Exceptions\BusinessException::class);
        app(OrderService::class)->changeStatus($order, OrderStatus::DELIVERED);
    }

    // ── 7. SNAPSHOT ────────────────────────────────────────────────────

    public function test_changing_the_product_later_does_not_touch_past_orders(): void
    {
        // CLAUDE.md §3.3
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 2]])->assertCreated();

        RestaurantContext::allowCrossRestaurant();
        $this->osh->update(['name_uz' => 'Boshqa nom', 'name_ru' => 'Другое', 'price' => 99000]);

        $item = Order::firstOrFail()->items()->firstOrFail();

        $this->assertSame('Osh', $item->product_name_uz_snapshot);
        $this->assertSame('Плов', $item->product_name_ru_snapshot);
        $this->assertSame(45000.0, $item->price_snapshot);
        $this->assertSame(90000.0, Order::firstOrFail()->total);
    }

    // ── 9. IZOH ────────────────────────────────────────────────────────

    public function test_the_customer_note_is_stored(): void
    {
        $response = $this->submit(
            [['product_id' => $this->osh->id, 'quantity' => 1, 'note' => 'Achchiq qilmang']],
            null,
            ['note' => 'Tez keltiring'],
        )->assertCreated();

        $this->assertSame('Tez keltiring', $response->json('data.order.note'));
        $this->assertSame('Achchiq qilmang', $response->json('data.order.items.0.note'));
    }

    // ── 8. STATUS EKRANI ───────────────────────────────────────────────

    public function test_the_customer_can_poll_their_order_status(): void
    {
        $id = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->assertCreated()->json('data.order.id');

        $response = $this->withHeader('X-Customer-Token', $this->token)
            ->getJson("/api/v1/orders/{$id}")
            ->assertOk();

        $this->assertSame(OrderStatus::PENDING->value, $response->json('data.order.status'));
        $this->assertFalse($response->json('data.order.is_final'));
    }

    public function test_is_final_flips_once_delivered_so_polling_can_stop(): void
    {
        $id = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->json('data.order.id');

        RestaurantContext::allowCrossRestaurant();
        $order = Order::findOrFail($id);
        $service = app(OrderService::class);
        foreach ([
            OrderStatus::ACCEPTED, OrderStatus::ASSIGNED,
            OrderStatus::WAITER_ACCEPTED, OrderStatus::DELIVERING, OrderStatus::DELIVERED,
        ] as $status) {
            $order = $service->changeStatus($order, $status);
        }
        RestaurantContext::reset();

        $this->withHeader('X-Customer-Token', $this->token)
            ->getJson("/api/v1/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::DELIVERED->value)
            ->assertJsonPath('data.order.is_final', true);
    }

    public function test_there_is_no_kitchen_status_anywhere_in_the_flow(): void
    {
        // CLAUDE.md §2.1 — oshpaz tizimdan foydalanmaydi.
        $body = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->assertCreated()->content();

        $this->assertStringNotContainsStringIgnoringCase('kitchen', $body);
        $this->assertStringNotContainsStringIgnoringCase('oshpaz', $body);
    }

    public function test_another_customer_cannot_read_this_order(): void
    {
        $id = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->json('data.order.id');

        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        $otherTable = Table::create([
            'restaurant_id' => $other->id, 'number' => 1,
            'capacity' => 4, 'nfc_token' => str_repeat('b', 64),
        ]);
        RestaurantContext::reset();

        $foreignToken = $this->postJson("/api/v1/t/{$otherTable->nfc_token}/sessions", [
            'guest_count' => 2,
        ])->json('data.customer_token');

        $this->withHeader('X-Customer-Token', $foreignToken)
            ->getJson("/api/v1/orders/{$id}")
            ->assertNotFound();
    }

    public function test_the_order_response_never_leaks_internal_identifiers(): void
    {
        $order = $this->submit([['product_id' => $this->osh->id, 'quantity' => 1]])
            ->json('data.order');

        foreach (['restaurant_id', 'table_id', 'session_id', 'waiter_id', 'client_order_uuid'] as $key) {
            $this->assertArrayNotHasKey($key, $order);
        }
    }

    public function test_the_session_total_follows_the_orders(): void
    {
        $this->submit([['product_id' => $this->osh->id, 'quantity' => 2]])->assertCreated();

        $this->withHeader('X-Customer-Token', $this->token)
            ->getJson('/api/v1/sessions/me')
            ->assertOk()
            ->assertJsonPath('data.session.total_amount', 90000)
            ->assertJsonCount(1, 'data.orders');
    }
}
