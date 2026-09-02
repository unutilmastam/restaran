<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Restaurant;
use App\Models\SessionDevice;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\SessionService;
use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** docs/01-ARCHITECTURE.md §12 + docs/03-PHASES.md PHASE 4. */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();
        $this->restaurant = Restaurant::factory()->create(['slug' => 'demo']);
        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 2,
            'capacity' => 4,
            'nfc_token' => str_repeat('a', 64),
        ]);
        RestaurantContext::reset();
    }

    private function open(int $guests = 3): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", [
            'guest_count' => $guests,
        ]);
    }

    public function test_opening_a_session_returns_a_token_and_creates_the_row(): void
    {
        $response = $this->open(3)->assertCreated();

        $this->assertSame(SessionStatus::ACTIVE->value, $response->json('data.session.status'));
        $this->assertSame(3, $response->json('data.session.guest_count'));
        $this->assertTrue($response->json('data.session.can_order'));

        $this->assertDatabaseCount('table_sessions', 1);
        $this->assertDatabaseCount('session_devices', 1);
    }

    public function test_the_plaintext_token_is_returned_once_and_only_its_hash_is_stored(): void
    {
        // docs/05-PHASE0-PLAN.md §2.3 — token bearer, ochiq saqlanmaydi.
        $token = $this->open()->json('data.customer_token');

        $this->assertSame(SessionService::TOKEN_LENGTH, strlen($token));
        $this->assertDatabaseMissing('session_devices', ['customer_token_hash' => $token]);
        $this->assertDatabaseHas('session_devices', [
            'customer_token_hash' => hash('sha256', $token),
        ]);
    }

    public function test_the_hash_is_deterministic_so_lookup_by_token_works(): void
    {
        // bcrypt EMAS: har safar boshqa hash bersa `where` qidiruv
        // ishlamas va indeks foydasiz bo'lardi.
        $token = $this->open()->json('data.customer_token');

        $this->assertSame(SessionDevice::hashToken($token), SessionDevice::hashToken($token));

        $this->withHeader('X-Customer-Token', $token)
            ->getJson('/api/v1/sessions/me')
            ->assertOk()
            ->assertJsonPath('data.table.number', 2);
    }

    public function test_the_session_never_exposes_its_database_id(): void
    {
        // docs/03-PHASES.md PHASE 4 — predictable `session_id=501` yo'q.
        $data = $this->open()->json('data.session');

        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayNotHasKey('restaurant_id', $data);
        $this->assertArrayNotHasKey('table_id', $data);
        $this->assertSame(32, strlen($data['public_id']));
    }

    public function test_a_second_phone_joins_the_same_session_with_its_own_token(): void
    {
        // Javob 9: bir stolda bir necha telefon BITTA hisobni bo'lishadi.
        $first = $this->open(3)->assertCreated()->json('data');
        $second = $this->open(2)->assertOk()->json('data');

        $this->assertSame($first['session']['public_id'], $second['session']['public_id']);
        $this->assertNotSame($first['customer_token'], $second['customer_token']);
        // guest_count birinchi qurilmaniki bo'lib qoladi.
        $this->assertSame(3, $second['session']['guest_count']);

        $this->assertDatabaseCount('table_sessions', 1);
        $this->assertDatabaseCount('session_devices', 2);
    }

    public function test_both_phones_can_read_the_same_session(): void
    {
        $first = $this->open()->json('data.customer_token');
        $second = $this->open()->json('data.customer_token');

        foreach ([$first, $second] as $token) {
            $this->withHeader('X-Customer-Token', $token)
                ->getJson('/api/v1/sessions/me')
                ->assertOk();
        }
    }

    public function test_scanning_with_an_active_session_reconnects_instead_of_asking_for_guests(): void
    {
        // docs/01 §12: ACTIVE → ulanadi.
        $this->open(4);

        $this->getJson("/api/v1/t/{$this->table->nfc_token}")
            ->assertOk()
            ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value)
            ->assertJsonPath('data.session.guest_count', 4)
            ->assertJsonPath('data.can_order', true)
            ->assertJsonPath('data.order_blocked_reason', null);
    }

    public function test_waiting_payment_keeps_the_menu_open_but_blocks_ordering(): void
    {
        // docs/01 §12 — eng muhim edge case.
        $this->open();

        RestaurantContext::allowCrossRestaurant();
        TableSession::query()->update(['status' => SessionStatus::WAITING_PAYMENT]);
        RestaurantContext::reset();

        $this->getJson("/api/v1/t/{$this->table->nfc_token}")
            ->assertOk()
            ->assertJsonPath('data.session.status', SessionStatus::WAITING_PAYMENT->value)
            ->assertJsonPath('data.can_order', false)
            ->assertJsonPath('data.order_blocked_reason', 'SESSION_WAITING_PAYMENT')
            ->assertJsonPath('data.session.can_order', false);

        // Menyu OCHIQ qoladi — mijoz cart tayyorlashi mumkin.
        $this->getJson("/api/v1/t/{$this->table->nfc_token}/menu")->assertOk();
    }

    public function test_a_closed_session_frees_the_table_for_a_new_one(): void
    {
        // docs/01 §12: CLOSED → yangi session.
        $first = $this->open(3)->json('data.session.public_id');

        RestaurantContext::allowCrossRestaurant();
        app(SessionService::class)->closeSession(TableSession::firstOrFail());
        RestaurantContext::reset();

        $second = $this->open(2)->assertCreated()->json('data.session.public_id');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('table_sessions', 2);
    }

    public function test_the_table_status_follows_the_session(): void
    {
        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);

        $this->open();
        $this->assertSame(TableStatus::ACTIVE, $this->table->fresh()->status);

        RestaurantContext::allowCrossRestaurant();
        app(SessionService::class)->closeSession(TableSession::firstOrFail());
        RestaurantContext::reset();

        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);
    }

    public function test_an_unknown_or_missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/sessions/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_NOT_FOUND');

        $this->withHeader('X-Customer-Token', str_repeat('z', 64))
            ->getJson('/api/v1/sessions/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'SESSION_NOT_FOUND');
    }

    public function test_a_token_from_another_restaurant_cannot_read_this_session(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        $otherTable = Table::create([
            'restaurant_id' => $other->id,
            'number' => 1, 'capacity' => 4, 'nfc_token' => str_repeat('b', 64),
        ]);
        RestaurantContext::reset();

        $otherToken = $this->postJson("/api/v1/t/{$otherTable->nfc_token}/sessions", [
            'guest_count' => 2,
        ])->json('data.customer_token');

        $this->open();

        // Boshqa restoran tokeni O'Z sessionini ko'radi, bizinikini emas.
        $this->withHeader('X-Customer-Token', $otherToken)
            ->getJson('/api/v1/sessions/me')
            ->assertOk()
            ->assertJsonPath('data.table.number', 1);
    }

    public function test_guest_count_is_validated(): void
    {
        $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", ['guest_count' => 0])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED');

        $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", ['guest_count' => 999])
            ->assertStatus(422);

        $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", [])
            ->assertStatus(422);
    }

    public function test_the_customer_cannot_inject_a_restaurant_or_status(): void
    {
        // docs/01-ARCHITECTURE.md §13
        RestaurantContext::allowCrossRestaurant();
        $other = Restaurant::factory()->create();
        RestaurantContext::reset();

        $this->postJson("/api/v1/t/{$this->table->nfc_token}/sessions", [
            'guest_count' => 2,
            'restaurant_id' => $other->id,
            'status' => SessionStatus::PAID->value,
            'total_amount' => 999999,
        ])->assertCreated();

        RestaurantContext::allowCrossRestaurant();
        $session = TableSession::firstOrFail();

        $this->assertSame($this->restaurant->id, $session->restaurant_id);
        $this->assertSame(SessionStatus::ACTIVE, $session->status);
        $this->assertSame(0.0, $session->total_amount);
    }

    public function test_an_expired_restaurant_cannot_open_a_session(): void
    {
        RestaurantContext::allowCrossRestaurant();
        $this->restaurant->update([
            'subscription_status' => \App\Enums\SubscriptionStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
        RestaurantContext::reset();

        $this->open()->assertForbidden()->assertJsonPath('error_code', 'RESTAURANT_UNAVAILABLE');
    }
}
