<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\WaiterStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use App\Support\RestaurantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * docs/01-ARCHITECTURE.md §7 — avtomatik biriktirish RAQOBATDA.
 *
 * Savol: ikki (yoki sakkiz) admin AYNAN bir vaqtda turli orderlarni
 * ACCEPT bosса, bitta bo'sh afitsantga ikkalasi ham tushib qoladimi?
 *
 * `RefreshDatabase` ATAYIN ISHLATILMAYDI: u hamma narsani BITTA
 * ochiq transaction ichida ushlab turadi, ya'ni worker processlar
 * yaratilgan ma'lumotni umuman ko'rmaydi va `lockForUpdate()` sinalmaydi.
 *
 *     php artisan test --group=concurrency
 */
#[Group('concurrency')]
class AssignmentConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private Restaurant $restaurant;

    private Table $table;

    private TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create(['slug' => 'assignment']);
        $this->table = Table::create([
            'restaurant_id' => $this->restaurant->id,
            'number' => 1, 'capacity' => 4, 'nfc_token' => str_repeat('d', 64),
        ]);
        $this->session = TableSession::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_count' => 2,
            'status' => SessionStatus::ACTIVE,
            'public_id' => Str::random(32),
            'opened_at' => now(),
        ]);

        RestaurantContext::reset();
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    /**
     * ASOSIY TALAB: bo'sh afitsant 3 ta, ACCEPT 8 ta — aynan 3 tasi
     * biriktiriladi, qolgan 5 tasi navbatda qoladi va HECH BIR afitsant
     * ikkita order olib qolmaydi.
     */
    public function test_parallel_accepts_never_give_one_waiter_two_orders(): void
    {
        $waiters = $this->waiters(3);
        $orders = $this->orders(self::WORKERS);

        $results = $this->runWorkers($orders);

        foreach ($results as $index => $result) {
            $this->assertTrue($result['ok'], "Worker #{$index}: ".($result['error'] ?? '?'));
        }

        $assigned = array_values(array_filter(
            $results,
            static fn (array $r): bool => $r['status'] === OrderStatus::ASSIGNED->value,
        ));
        $queued = array_values(array_filter(
            $results,
            static fn (array $r): bool => $r['status'] === OrderStatus::WAITING_FOR_WAITER->value,
        ));

        $this->assertCount(3, $assigned, 'Bo\'sh afitsantlar sonidan ko\'p/kam order biriktirildi.');
        $this->assertCount(self::WORKERS - 3, $queued);

        // ⚠️ ENG MUHIM TEKSHIRUV: bitta waiter_id ikki marta uchramasin.
        $waiterIds = array_column($assigned, 'waiter_id');
        $this->assertCount(
            3,
            array_unique($waiterIds),
            'Bitta afitsantga bir nechta order tushdi: '.implode(', ', $waiterIds),
        );

        // DB ham shuni tasdiqlaydi (worker javobiga emas, jadvalga qaraymiz).
        $perWaiter = DB::table('orders')
            ->whereNotNull('waiter_id')
            ->selectRaw('waiter_id, COUNT(*) as total')
            ->groupBy('waiter_id')
            ->pluck('total', 'waiter_id');

        $this->assertCount(3, $perWaiter);

        foreach ($perWaiter as $waiterId => $total) {
            $this->assertSame(1, (int) $total, "Afitsant #{$waiterId} da {$total} ta order.");
        }

        // Biriktirilgan afitsantlar BUSY, ular endi hech kimga tanlanmaydi.
        $this->assertSame(3, DB::table('users')
            ->whereIn('id', $waiters->pluck('id'))
            ->where('status', WaiterStatus::BUSY->value)
            ->count());

        // Navbatdagilar hech kimga biriktirilmagan.
        $this->assertSame(self::WORKERS - 3, DB::table('orders')
            ->where('status', OrderStatus::WAITING_FOR_WAITER->value)
            ->whereNull('waiter_id')
            ->count());
    }

    /** Afitsant yetarli bo'lsa — har biriga aynan bittadan tushadi. */
    public function test_with_enough_waiters_every_order_gets_its_own(): void
    {
        $this->waiters(self::WORKERS);
        $orders = $this->orders(self::WORKERS);

        $results = $this->runWorkers($orders);

        foreach ($results as $index => $result) {
            $this->assertTrue($result['ok'], "Worker #{$index}: ".($result['error'] ?? '?'));
            $this->assertSame(OrderStatus::ASSIGNED->value, $result['status']);
        }

        $this->assertCount(self::WORKERS, array_unique(array_column($results, 'waiter_id')));
        $this->assertSame(0, DB::table('orders')
            ->where('status', OrderStatus::WAITING_FOR_WAITER->value)
            ->count());
    }

    /** Bo'sh afitsant umuman yo'q — hammasi navbatga, hech kim yo'qolmaydi. */
    public function test_with_no_free_waiter_all_parallel_accepts_queue(): void
    {
        $this->waiters(2, WaiterStatus::BUSY);
        $orders = $this->orders(self::WORKERS);

        $results = $this->runWorkers($orders);

        foreach ($results as $index => $result) {
            $this->assertTrue($result['ok'], "Worker #{$index}: ".($result['error'] ?? '?'));
            $this->assertSame(OrderStatus::WAITING_FOR_WAITER->value, $result['status']);
        }

        $this->assertSame(self::WORKERS, DB::table('orders')
            ->where('status', OrderStatus::WAITING_FOR_WAITER->value)
            ->whereNull('waiter_id')
            ->count());
    }

    /** @return Collection<int, User> */
    private function waiters(int $count, WaiterStatus $status = WaiterStatus::FREE): Collection
    {
        RestaurantContext::allowCrossRestaurant();

        $waiters = collect(range(1, $count))->map(fn (int $i): User => User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => "Afitsant {$i}",
            'username' => "waiter{$i}",
            'status' => $status,
            // Teng yuk — tanlov `last_free_at` bo'yicha ketadi.
            'last_free_at' => now()->subMinutes($i),
        ]));

        RestaurantContext::reset();

        return $waiters;
    }

    /** @return list<int> */
    private function orders(int $count): array
    {
        RestaurantContext::allowCrossRestaurant();

        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $ids[] = Order::create([
                'restaurant_id' => $this->restaurant->id,
                'table_id' => $this->table->id,
                'session_id' => $this->session->id,
                'client_order_uuid' => (string) Str::uuid(),
                'order_number' => sprintf('#%04d', $i),
                'business_date' => now()->toDateString(),
                'status' => OrderStatus::PENDING,
                'guest_count' => 2,
                'subtotal' => 45000, 'discount' => 0, 'total' => 45000,
            ])->id;
        }

        RestaurantContext::reset();

        return $ids;
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<array{ok: bool, id?: int, status?: string, waiter_id?: int|null, error?: string}>
     */
    private function runWorkers(array $orderIds): array
    {
        $worker = __DIR__.'/assignment_worker.php';
        // Barcha processlar shu daqiqada birdaniga "ACCEPT" bosadi.
        $startAt = microtime(true) + 1.5;

        $processes = [];
        $pipes = [];

        foreach ($orderIds as $orderId) {
            $process = proc_open(
                [PHP_BINARY, $worker, (string) $orderId, (string) $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $workerPipes,
                base_path(),
                ['DB_DATABASE' => config('database.connections.mysql.database')] + getenv(),
            );

            $this->assertIsResource($process);

            $processes[] = $process;
            $pipes[] = $workerPipes;
        }

        $results = [];

        foreach ($processes as $index => $process) {
            $stdout = trim((string) stream_get_contents($pipes[$index][1]));
            $stderr = trim((string) stream_get_contents($pipes[$index][2]));

            fclose($pipes[$index][1]);
            fclose($pipes[$index][2]);
            proc_close($process);

            $decoded = json_decode($stdout, true);

            $this->assertIsArray(
                $decoded,
                "Worker #{$index} JSON qaytarmadi.\nSTDOUT: {$stdout}\nSTDERR: {$stderr}"
            );

            $results[] = $decoded;
        }

        return $results;
    }
}
