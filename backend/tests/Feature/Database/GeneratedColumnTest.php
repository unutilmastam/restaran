<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/07-DB-DECISIONS.md §6 — generated column + UNIQUE.
 *
 * Bu kafolatlar DB DARAJASIDA ishlaydi: kod xatosi ham ularni buza olmaydi.
 */
class GeneratedColumnTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();
        $this->restaurant = Restaurant::factory()->create();
    }

    private function table(int $number = 1): Table
    {
        return Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => $number,
            'capacity' => 4,
            'nfc_token' => 'tok-'.$number.'-'.uniqid(),
        ]);
    }

    private function openSession(Table $table, SessionStatus $status, string $publicId): TableSession
    {
        return TableSession::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'guest_count' => 2,
            'status' => $status,
            'public_id' => $publicId,
            'opened_at' => now(),
        ]);
    }

    public function test_a_table_cannot_have_two_open_sessions(): void
    {
        $table = $this->table();
        $this->openSession($table, SessionStatus::ACTIVE, 'p1');

        $this->expectException(QueryException::class);
        $this->openSession($table, SessionStatus::ACTIVE, 'p2');
    }

    public function test_waiting_payment_also_occupies_the_table(): void
    {
        $table = $this->table();
        $this->openSession($table, SessionStatus::WAITING_PAYMENT, 'p1');

        $this->expectException(QueryException::class);
        $this->openSession($table, SessionStatus::ACTIVE, 'p2');
    }

    public function test_a_new_session_opens_once_the_previous_one_closes(): void
    {
        $table = $this->table();
        $first = $this->openSession($table, SessionStatus::ACTIVE, 'p1');

        $first->update(['status' => SessionStatus::CLOSED, 'closed_at' => now()]);
        $second = $this->openSession($table, SessionStatus::ACTIVE, 'p2');

        $this->assertDatabaseCount('table_sessions', 2);
        $this->assertSame(SessionStatus::ACTIVE, $second->status);
    }

    public function test_many_closed_sessions_can_coexist_on_one_table(): void
    {
        $table = $this->table();

        foreach (['c1', 'c2', 'c3'] as $publicId) {
            $this->openSession($table, SessionStatus::CLOSED, $publicId);
        }

        $this->assertDatabaseCount('table_sessions', 3);
    }

    public function test_two_tables_can_each_have_an_open_session(): void
    {
        $this->openSession($this->table(1), SessionStatus::ACTIVE, 'p1');
        $this->openSession($this->table(2), SessionStatus::ACTIVE, 'p2');

        $this->assertDatabaseCount('table_sessions', 2);
    }

    public function test_a_restaurant_cannot_have_two_owner_admins(): void
    {
        User::factory()->ownerAdmin()->create(['restaurant_id' => $this->restaurant->id]);

        $this->expectException(QueryException::class);
        User::factory()->ownerAdmin()->create(['restaurant_id' => $this->restaurant->id]);
    }

    public function test_a_restaurant_can_have_many_plain_admins(): void
    {
        User::factory()->ownerAdmin()->create(['restaurant_id' => $this->restaurant->id]);
        User::factory()->admin()->count(3)->create(['restaurant_id' => $this->restaurant->id]);

        $this->assertSame(3, User::withoutGlobalScopes()
            ->where('role', UserRole::ADMIN)->count());
    }

    public function test_each_restaurant_gets_its_own_owner_admin(): void
    {
        $other = Restaurant::factory()->create();

        User::factory()->ownerAdmin()->create(['restaurant_id' => $this->restaurant->id]);
        User::factory()->ownerAdmin()->create(['restaurant_id' => $other->id]);

        $this->assertSame(2, User::withoutGlobalScopes()
            ->where('role', UserRole::OWNER_ADMIN)->count());
    }

    public function test_several_super_admins_can_exist_with_a_null_restaurant(): void
    {
        User::factory()->superAdmin()->count(2)->create();

        $this->assertSame(2, User::withoutGlobalScopes()
            ->whereNull('restaurant_id')->count());
    }
}
