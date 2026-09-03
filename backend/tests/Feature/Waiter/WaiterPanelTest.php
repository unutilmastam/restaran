<?php

declare(strict_types=1);

namespace Tests\Feature\Waiter;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\WaiterStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * docs/03-PHASES.md PHASE 7.
 *
 * ⚠️ BARCHA testlar HAQIQIY TOKEN bilan ishlaydi, `actingAs()` bilan
 * EMAS. Sabab: PHASE 6 da `actingAs()` autentifikatsiyani chetlab
 * o'tgani uchun Sanctum global scope xatosi 30 ta testdan o'tib ketgan
 * va faqat brauzerda ko'ringan edi.
 */
class WaiterPanelTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $hasan;

    private User $akmal;

    private Table $table;

    private TableSession $session;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create(['slug' => 'demo']);

        $this->hasan = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Hasan',
            'username' => 'hasan',
            'password' => 'password',
            'pin' => '1234',
            'status' => WaiterStatus::FREE,
        ]);

        $this->akmal = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Akmal',
            'username' => 'akmal',
            'password' => 'password',
            'status' => WaiterStatus::FREE,
        ]);

        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 2, 'capacity' => 4, 'nfc_token' => str_repeat('a', 64),
        ]);

        $this->session = TableSession::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_count' => 3,
            'status' => SessionStatus::ACTIVE,
            'public_id' => Str::random(32),
            'opened_at' => now(),
        ]);

        RestaurantContext::reset();

        $this->token = $this->login('hasan', 'password');
    }

    private function login(string $login, ?string $password = null, ?string $pin = null): string
    {
        return $this->postJson('/api/v1/auth/login', array_filter([
            'login' => $login,
            'password' => $password,
            'pin' => $pin,
        ]))->assertOk()->json('data.token');
    }

    /** Haqiqiy Bearer token bilan so'rov. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function makeOrder(User $waiter, OrderStatus $status = OrderStatus::ASSIGNED): Order
    {
        RestaurantContext::allowCrossRestaurant();

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_id' => $this->session->id,
            'waiter_id' => $waiter->id,
            'client_order_uuid' => (string) Str::uuid(),
            'order_number' => '#'.str_pad((string) (Order::withoutGlobalScopes()->count() + 1), 4, '0', STR_PAD_LEFT),
            'business_date' => now()->toDateString(),
            'status' => $status,
            'guest_count' => 3,
            'subtotal' => 45000, 'discount' => 0, 'total' => 45000,
            'assigned_at' => now(),
        ]);

        RestaurantContext::reset();

        return $order;
    }

    // ── 1. AUTH ────────────────────────────────────────────────────────

    public function test_a_waiter_can_log_in_with_a_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'hasan', 'password' => 'password',
        ])->assertOk();

        $this->assertSame('WAITER', $response->json('data.user.role'));
        $this->assertSame('Hasan', $response->json('data.user.name'));
    }

    public function test_a_waiter_can_log_in_with_a_pin(): void
    {
        // Telefonda tez kirish uchun.
        $token = $this->login('hasan', pin: '1234');

        $this->as($token)->getJson('/api/v1/waiter/profile')
            ->assertOk()
            ->assertJsonPath('data.waiter.name', 'Hasan');
    }

    public function test_a_wrong_pin_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['login' => 'hasan', 'pin' => '9999'])
            ->assertStatus(422);
    }

    public function test_the_token_works_on_every_waiter_route(): void
    {
        // PHASE 6 dagi Sanctum xatosi shu yerda ham takrorlanmasin.
        foreach (['/api/v1/waiter/profile', '/api/v1/waiter/orders', '/api/v1/waiter/history', '/api/v1/waiter/calls'] as $route) {
            $this->as($this->token)->getJson($route)->assertOk();
        }
    }

    public function test_a_waiter_cannot_reach_the_admin_panel(): void
    {
        $this->as($this->token)->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_an_admin_cannot_reach_the_waiter_panel(): void
    {
        RestaurantContext::allowCrossRestaurant();
        User::factory()->ownerAdmin()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'owner', 'password' => 'password',
        ]);
        RestaurantContext::reset();

        $this->as($this->login('owner', 'password'))
            ->getJson('/api/v1/waiter/orders')
            ->assertForbidden();
    }

    // ── 2-3. BOSH EKRAN VA RO'YXAT ─────────────────────────────────────

    public function test_the_profile_shows_the_name_and_status(): void
    {
        $this->as($this->token)->getJson('/api/v1/waiter/profile')
            ->assertOk()
            ->assertJsonPath('data.waiter.name', 'Hasan')
            ->assertJsonPath('data.waiter.status', WaiterStatus::FREE->value)
            ->assertJsonPath('data.today.active', 0);
    }

    public function test_a_waiter_only_sees_orders_assigned_to_them(): void
    {
        $mine = $this->makeOrder($this->hasan);
        $this->makeOrder($this->akmal);

        $items = $this->as($this->token)->getJson('/api/v1/waiter/orders')
            ->assertOk()->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame($mine->id, $items[0]['id']);
        $this->assertSame(2, $items[0]['table']['number']);
    }

    public function test_delivered_orders_leave_the_active_list_and_land_in_history(): void
    {
        $order = $this->makeOrder($this->hasan);

        foreach (['accept', 'delivering', 'deliver'] as $action) {
            $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/{$action}")->assertOk();
        }

        $this->assertCount(0, $this->as($this->token)->getJson('/api/v1/waiter/orders')->json('data.items'));
        $this->assertCount(1, $this->as($this->token)->getJson('/api/v1/waiter/history')->json('data.items'));
    }

    // ── 4-6. OQIM VA STATUS ────────────────────────────────────────────

    public function test_accepting_an_order_makes_the_waiter_busy(): void
    {
        $order = $this->makeOrder($this->hasan);

        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::WAITER_ACCEPTED->value);

        RestaurantContext::allowCrossRestaurant();
        $this->assertSame(WaiterStatus::BUSY, $this->hasan->refresh()->status);
    }

    public function test_the_full_flow_ends_with_the_waiter_free_again(): void
    {
        $order = $this->makeOrder($this->hasan);

        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/accept")->assertOk();
        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/delivering")
            ->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::DELIVERING->value);

        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::DELIVERED->value);

        RestaurantContext::allowCrossRestaurant();
        $this->hasan->refresh();

        $this->assertSame(WaiterStatus::FREE, $this->hasan->status);
        // last_free_at — WaiterAssignmentService uchun (docs/01 §7).
        $this->assertNotNull($this->hasan->last_free_at);
        $this->assertNotNull(Order::findOrFail($order->id)->delivered_at);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $order = $this->makeOrder($this->hasan);

        // ASSIGNED dan to'g'ridan-to'g'ri DELIVERED ga sakrash mumkin emas.
        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/deliver")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_a_waiter_with_an_open_order_cannot_mark_themselves_free(): void
    {
        $order = $this->makeOrder($this->hasan);
        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$order->id}/accept")->assertOk();

        $this->as($this->token)->postJson('/api/v1/waiter/status', ['status' => WaiterStatus::FREE->value])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ORDER_NOT_DELIVERED');
    }

    public function test_a_waiter_can_go_offline_and_come_back(): void
    {
        $this->as($this->token)->postJson('/api/v1/waiter/status', ['status' => WaiterStatus::OFFLINE->value])
            ->assertOk()
            ->assertJsonPath('data.waiter.status', WaiterStatus::OFFLINE->value);

        $this->as($this->token)->postJson('/api/v1/waiter/status', ['status' => WaiterStatus::FREE->value])
            ->assertOk()
            ->assertJsonPath('data.waiter.status', WaiterStatus::FREE->value);
    }

    public function test_busy_cannot_be_set_by_hand(): void
    {
        // docs/01-ARCHITECTURE.md §3 — BUSY ni tizim qo'yadi.
        $this->as($this->token)->postJson('/api/v1/waiter/status', ['status' => WaiterStatus::BUSY->value])
            ->assertStatus(422);
    }

    // ── 10. IZOLYATSIYA ────────────────────────────────────────────────

    public function test_a_waiter_cannot_touch_another_waiters_order(): void
    {
        // docs/04-TEST-SCENARIO.md "Waiter izolyatsiyasi"
        $foreign = $this->makeOrder($this->akmal);

        foreach (['accept', 'delivering', 'deliver'] as $action) {
            $this->as($this->token)->postJson("/api/v1/waiter/orders/{$foreign->id}/{$action}")
                ->assertForbidden()
                ->assertJsonPath('error_code', 'FORBIDDEN');
        }

        RestaurantContext::allowCrossRestaurant();
        $this->assertSame(OrderStatus::ASSIGNED, $foreign->refresh()->status);
    }

    public function test_a_waiter_cannot_touch_an_order_from_another_restaurant(): void
    {
        RestaurantContext::allowCrossRestaurant();

        $other = Restaurant::factory()->create();
        $otherTable = Table::create([
            'restaurant_id' => $other->id, 'number' => 1,
            'capacity' => 4, 'nfc_token' => str_repeat('b', 64),
        ]);
        $otherWaiter = User::factory()->create(['restaurant_id' => $other->id]);
        $foreign = Order::create([
            'restaurant_id' => $other->id,
            'table_id' => $otherTable->id,
            'waiter_id' => $otherWaiter->id,
            'client_order_uuid' => (string) Str::uuid(),
            'order_number' => '#0001',
            'business_date' => now()->toDateString(),
            'status' => OrderStatus::ASSIGNED,
            'guest_count' => 2,
            'subtotal' => 1000, 'discount' => 0, 'total' => 1000,
        ]);

        RestaurantContext::reset();

        // Global scope uni umuman ko'rsatmaydi — 404.
        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$foreign->id}/accept")
            ->assertNotFound();
    }

    public function test_an_unassigned_order_cannot_be_grabbed(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $unassigned = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_id' => $this->session->id,
            'client_order_uuid' => (string) Str::uuid(),
            'order_number' => '#0099',
            'business_date' => now()->toDateString(),
            'status' => OrderStatus::PENDING,
            'guest_count' => 2,
            'subtotal' => 1000, 'discount' => 0, 'total' => 1000,
        ]);
        RestaurantContext::reset();

        $this->as($this->token)->postJson("/api/v1/waiter/orders/{$unassigned->id}/accept")
            ->assertForbidden();
    }

    // ── 7. CHAQIRUVLAR (skeleton) ──────────────────────────────────────

    public function test_the_calls_endpoint_answers_with_an_empty_list_for_now(): void
    {
        // To'liq oqim PHASE 11 da.
        $this->as($this->token)->getJson('/api/v1/waiter/calls')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
