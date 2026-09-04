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
use App\Services\OrderService;
use App\Services\WaiterAssignmentService;
use App\Services\WaiterService;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** docs/01-ARCHITECTURE.md §7 algoritmi — har bir qadam alohida. */
class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Table $table;

    private TableSession $session;

    private WaiterAssignmentService $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create(['slug' => 'demo']);
        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 1, 'capacity' => 4, 'nfc_token' => str_repeat('a', 64),
        ]);
        $this->session = TableSession::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_count' => 2,
            'status' => SessionStatus::ACTIVE,
            'public_id' => Str::random(32),
            'opened_at' => now(),
        ]);

        $this->assignment = app(WaiterAssignmentService::class);
    }

    private function waiter(string $name, WaiterStatus $status = WaiterStatus::FREE, ?string $freeAt = null): User
    {
        return User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => $name,
            'username' => Str::lower($name).Str::random(4),
            'status' => $status,
            'last_free_at' => $freeAt === null ? now() : now()->parse($freeAt),
        ]);
    }

    private function order(?User $waiter = null, OrderStatus $status = OrderStatus::PENDING, bool $draft = false): Order
    {
        return Order::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'session_id' => $draft ? null : $this->session->id,
            'waiter_id' => $waiter?->id,
            'client_order_uuid' => (string) Str::uuid(),
            'order_number' => '#'.str_pad((string) (Order::withoutGlobalScopes()->count() + 1), 4, '0', STR_PAD_LEFT),
            'business_date' => now()->toDateString(),
            'status' => $draft ? OrderStatus::DRAFT : $status,
            'guest_count' => 2,
            'subtotal' => 45000, 'discount' => 0, 'total' => 45000,
        ]);
    }

    // ── 1-2. ENG KAM YUK ───────────────────────────────────────────────

    public function test_the_least_loaded_waiter_is_chosen(): void
    {
        $busyish = $this->waiter('Hasan');
        $idle = $this->waiter('Akmal');

        // Hasan'da allaqachon bitta ochiq order bor.
        $this->order($busyish, OrderStatus::ASSIGNED);

        $assigned = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame($idle->id, $assigned->waiter_id);
        $this->assertSame(OrderStatus::ASSIGNED, $assigned->status);
    }

    public function test_delivered_orders_do_not_count_towards_load(): void
    {
        $veteran = $this->waiter('Hasan', freeAt: '-1 hour');
        $newcomer = $this->waiter('Akmal', freeAt: '-1 minute');

        // Hasan 5 ta orderni allaqachon YETKAZGAN — bu yuk emas.
        for ($i = 0; $i < 5; $i++) {
            $this->order($veteran, OrderStatus::DELIVERED);
        }

        $assigned = $this->assignment->acceptAndAssign($this->order());

        // Yuk teng (0/0), shuning uchun eng uzoq bo'sh turgani — Hasan.
        $this->assertSame($veteran->id, $assigned->waiter_id);
    }

    // ── 3. TENG YUK → last_free_at ──────────────────────────────────────

    public function test_on_equal_load_the_longest_idle_waiter_wins(): void
    {
        $recent = $this->waiter('Hasan', freeAt: '-1 minute');
        $longestIdle = $this->waiter('Akmal', freeAt: '-30 minutes');
        $middle = $this->waiter('Ali', freeAt: '-5 minutes');

        $assigned = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame($longestIdle->id, $assigned->waiter_id);
        $this->assertNotSame($recent->id, $assigned->waiter_id);
        $this->assertNotSame($middle->id, $assigned->waiter_id);
    }

    public function test_a_waiter_who_never_worked_is_first_in_line(): void
    {
        $this->waiter('Hasan', freeAt: '-30 minutes');

        $fresh = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Yangi', 'username' => 'yangi',
            'status' => WaiterStatus::FREE,
            'last_free_at' => null,
        ]);

        $assigned = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame($fresh->id, $assigned->waiter_id);
    }

    // ── 4. ASSIGN NATIJASI ─────────────────────────────────────────────

    public function test_assignment_marks_the_waiter_busy_and_stamps_the_time(): void
    {
        $waiter = $this->waiter('Hasan');

        $assigned = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame(WaiterStatus::BUSY, $waiter->refresh()->status);
        $this->assertNotNull($assigned->assigned_at);
        $this->assertNotNull($assigned->accepted_at);
    }

    // ── 5. NAVBAT ──────────────────────────────────────────────────────

    public function test_with_no_free_waiter_the_order_waits_in_the_queue(): void
    {
        $this->waiter('Hasan', WaiterStatus::BUSY);

        $queued = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame(OrderStatus::WAITING_FOR_WAITER, $queued->status);
        $this->assertNull($queued->waiter_id);
        $this->assertSame(1, $this->assignment->queuedCount($this->restaurant->id));
    }

    public function test_with_no_waiters_at_all_the_order_still_waits(): void
    {
        $queued = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame(OrderStatus::WAITING_FOR_WAITER, $queued->status);
    }

    // ── 6. BO'SHAGACH NAVBATDAN OLISH ──────────────────────────────────

    public function test_the_oldest_queued_order_is_picked_up_when_a_waiter_frees_up(): void
    {
        $waiter = $this->waiter('Hasan');

        // Birinchi order Hasan'ga tushadi.
        $first = $this->assignment->acceptAndAssign($this->order());
        $this->assertSame($waiter->id, $first->waiter_id);

        // Keyingi ikkitasi navbatda qoladi.
        $second = $this->assignment->acceptAndAssign($this->order());
        $third = $this->assignment->acceptAndAssign($this->order());

        $this->assertSame(OrderStatus::WAITING_FOR_WAITER, $second->status);
        $this->assertSame(OrderStatus::WAITING_FOR_WAITER, $third->status);

        // Hasan birinchisini yetkazadi.
        $orders = app(OrderService::class);
        foreach ([OrderStatus::WAITER_ACCEPTED, OrderStatus::DELIVERING] as $status) {
            $first = $orders->changeStatus($first, $status);
        }

        app(WaiterService::class)->deliver($waiter->refresh(), $first);

        // Navbatdagi ENG ESKI order (ikkinchisi) unga tushdi.
        $this->assertSame(OrderStatus::ASSIGNED, $second->refresh()->status);
        $this->assertSame($waiter->id, $second->waiter_id);
        // Uchinchisi hali kutadi.
        $this->assertSame(OrderStatus::WAITING_FOR_WAITER, $third->refresh()->status);
        $this->assertSame(WaiterStatus::BUSY, $waiter->refresh()->status);
    }

    public function test_nothing_happens_when_the_queue_is_empty(): void
    {
        $this->waiter('Hasan');

        $this->assertNull($this->assignment->assignNextQueued($this->restaurant->id));
    }

    // ── 5 (PHASE 5 qoidasi). DRAFT ─────────────────────────────────────

    public function test_a_draft_is_never_assigned(): void
    {
        // docs/05-PHASE0-PLAN.md §2.4
        $waiter = $this->waiter('Hasan');
        $draft = $this->order(draft: true);

        $result = $this->assignment->assign($draft);

        $this->assertSame(OrderStatus::DRAFT, $result->status);
        $this->assertNull($result->waiter_id);
        $this->assertSame(WaiterStatus::FREE, $waiter->refresh()->status);
    }

    // ── 6. OFFLINE ─────────────────────────────────────────────────────

    public function test_an_offline_waiter_is_skipped(): void
    {
        $offline = $this->waiter('Hasan', WaiterStatus::OFFLINE, '-1 hour');
        $online = $this->waiter('Akmal', freeAt: '-1 minute');

        $assigned = $this->assignment->acceptAndAssign($this->order());

        // Hasan uzoq bo'sh turgan bo'lsa ham OFFLINE — smenada emas.
        $this->assertSame($online->id, $assigned->waiter_id);
        $this->assertSame(WaiterStatus::OFFLINE, $offline->refresh()->status);
    }

    public function test_only_offline_waiters_means_the_order_queues(): void
    {
        $this->waiter('Hasan', WaiterStatus::OFFLINE);

        $this->assertSame(
            OrderStatus::WAITING_FOR_WAITER,
            $this->assignment->acceptAndAssign($this->order())->status,
        );
    }

    public function test_a_deactivated_waiter_is_skipped(): void
    {
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'ishdan-boshgan',
            'status' => WaiterStatus::FREE,
            'is_active' => false,
        ]);
        $active = $this->waiter('Akmal');

        $this->assertSame($active->id, $this->assignment->acceptAndAssign($this->order())->waiter_id);
    }

    // ── IZOLYATSIYA ────────────────────────────────────────────────────

    public function test_a_waiter_from_another_restaurant_is_never_chosen(): void
    {
        $other = Restaurant::factory()->create();
        User::factory()->create([
            'restaurant_id' => $other->id,
            'username' => 'begona',
            'status' => WaiterStatus::FREE,
            'last_free_at' => now()->subDay(),
        ]);

        $mine = $this->waiter('Hasan');

        $this->assertSame($mine->id, $this->assignment->acceptAndAssign($this->order())->waiter_id);
    }

    // ── ADMIN ENDPOINT ─────────────────────────────────────────────────

    public function test_accepting_through_the_admin_endpoint_assigns_in_one_step(): void
    {
        $waiter = $this->waiter('Hasan');
        $order = $this->order();

        $owner = User::factory()->ownerAdmin()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'owner', 'password' => 'password',
        ]);
        RestaurantContext::reset();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner', 'password' => 'password',
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/admin/orders/{$order->id}/accept")
            ->assertOk();

        $this->assertSame(OrderStatus::ASSIGNED->value, $response->json('data.order.status'));
        $this->assertSame('Hasan', $response->json('data.order.waiter.name'));

        RestaurantContext::allowCrossRestaurant();
        $this->assertSame(WaiterStatus::BUSY, $waiter->refresh()->status);
        unset($owner);
    }

    public function test_the_orders_endpoint_returns_new_and_queued_together(): void
    {
        // Bitta bo'sh afitsant — birinchi order unga, ikkinchisi navbatga.
        $this->waiter('Hasan');
        $this->assignment->acceptAndAssign($this->order());
        $this->assignment->acceptAndAssign($this->order());
        $pending = $this->order();

        User::factory()->ownerAdmin()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'owner', 'password' => 'password',
        ]);
        RestaurantContext::reset();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner', 'password' => 'password',
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/orders?status=PENDING,WAITING_FOR_WAITER')
            ->assertOk();

        $items = $response->json('data.items');
        $statuses = array_column($items, 'status');

        sort($statuses);
        $this->assertSame(
            [OrderStatus::PENDING->value, OrderStatus::WAITING_FOR_WAITER->value],
            $statuses,
        );
        // Yangi order ro'yxatda, ASSIGNED bo'lgani esa yo'q.
        $this->assertContains($pending->id, array_column($items, 'id'));
    }

    public function test_the_dashboard_reports_the_queue_and_free_waiters(): void
    {
        $this->waiter('Hasan', WaiterStatus::BUSY);
        $this->assignment->acceptAndAssign($this->order());

        User::factory()->ownerAdmin()->create([
            'restaurant_id' => $this->restaurant->id,
            'username' => 'owner', 'password' => 'password',
        ]);
        RestaurantContext::reset();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'owner', 'password' => 'password',
        ])->json('data.token');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            // Admin panelda "Barcha afitsantlar band" shu bo'yicha ko'rinadi.
            ->assertJsonPath('data.today.queued_orders', 1)
            ->assertJsonPath('data.today.free_waiters', 0);
    }
}
