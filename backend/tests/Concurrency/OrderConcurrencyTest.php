<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Enums\SessionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\TableSession;
use App\Support\RestaurantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * docs/04-TEST-SCENARIO.md "Idempotency" + docs/05-PHASE0-PLAN.md §2.5.
 *
 * `RefreshDatabase` ATAYIN ISHLATILMAYDI — sabab
 * `SessionConcurrencyTest` izohida batafsil.
 *
 *     php artisan test --group=concurrency
 */
#[Group('concurrency')]
class OrderConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private Table $table;

    private Product $product;

    private TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);

        RestaurantContext::allowCrossRestaurant();

        $restaurant = Restaurant::factory()->create(['slug' => 'concurrency']);
        $this->table = Table::create([
            'restaurant_id' => $restaurant->id,
            'number' => 1, 'capacity' => 4, 'nfc_token' => str_repeat('c', 64),
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name_ru' => 'Горячее', 'name_uz' => 'Issiq', 'slug' => 'hot',
        ]);

        $this->product = Product::create([
            'restaurant_id' => $restaurant->id, 'category_id' => $category->id,
            'name_ru' => 'Плов', 'name_uz' => 'Osh', 'price' => 45000,
        ]);

        $this->session = TableSession::create([
            'restaurant_id' => $restaurant->id,
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

    public function test_the_same_uuid_in_parallel_creates_exactly_one_order(): void
    {
        // Tugmani ikki marta bosish EMAS — 8 ta so'rov AYNAN bir vaqtda.
        $uuid = (string) Str::uuid();
        $results = $this->runWorkers(static fn (int $i): string => $uuid);

        foreach ($results as $index => $result) {
            $this->assertTrue($result['ok'], "Worker #{$index}: ".($result['error'] ?? '?'));
        }

        $this->assertSame(1, DB::table('orders')->count(), 'Bir xil uuid bilan bir nechta order yaratildi.');
        $this->assertSame(1, DB::table('order_items')->count());

        // Hamma worker O'SHA orderni ko'rdi.
        $this->assertCount(1, array_unique(array_column($results, 'id')));
    }

    public function test_parallel_orders_get_distinct_sequential_numbers(): void
    {
        // docs/05-PHASE0-PLAN.md §2.5 — order_counters + lockForUpdate.
        // Har worker BOSHQA uuid bilan, ya'ni 8 ta HAQIQIY order.
        //
        // Order lock (ORDER_NOT_DELIVERED) bu testga xalaqit bermasligi
        // uchun session'siz — draft rejimida yuriladi.
        RestaurantContext::allowCrossRestaurant();
        $this->session->update(['status' => SessionStatus::WAITING_PAYMENT]);
        RestaurantContext::reset();

        $results = $this->runWorkers(static fn (int $i): string => (string) Str::uuid());

        foreach ($results as $index => $result) {
            $this->assertTrue($result['ok'], "Worker #{$index}: ".($result['error'] ?? '?'));
        }

        $this->assertSame(self::WORKERS, DB::table('orders')->count());

        $numbers = array_column($results, 'order_number');
        $this->assertCount(
            self::WORKERS,
            array_unique($numbers),
            'order_number takrorlandi: '.implode(', ', $numbers)
        );

        // Ketma-ketlik uzilmagan: #0001 .. #0008
        sort($numbers);
        $this->assertSame(
            array_map(static fn (int $n): string => sprintf('#%04d', $n), range(1, self::WORKERS)),
            $numbers,
        );
    }

    public function test_the_daily_counter_holds_exactly_one_row(): void
    {
        $this->runWorkers(static fn (int $i): string => (string) Str::uuid());

        // `firstOrCreate` race'ga tushsa ham UNIQUE bitta qator qoldiradi.
        $this->assertSame(1, DB::table('order_counters')->count());
    }

    /**
     * @param  callable(int): string  $uuidFor
     * @return list<array{ok: bool, id?: int, order_number?: string, error?: string}>
     */
    private function runWorkers(callable $uuidFor): array
    {
        $worker = __DIR__.'/order_worker.php';
        $startAt = microtime(true) + 1.5;

        $processes = [];
        $pipes = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $process = proc_open(
                [
                    PHP_BINARY, $worker,
                    $this->table->nfc_token,
                    (string) $this->product->id,
                    $uuidFor($i),
                    (string) $startAt,
                ],
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
