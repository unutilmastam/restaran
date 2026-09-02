<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Enums\SessionStatus;
use App\Models\Restaurant;
use App\Models\Table;
use App\Support\RestaurantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * docs/04-TEST-SCENARIO.md "Concurrency" + docs/05-PHASE0-PLAN.md §4.3.
 *
 * ⚠️ `RefreshDatabase` ATAYIN ISHLATILMAYDI. U har testni transaction
 * ichida yuritadi, shuning uchun:
 *   - `lockForUpdate()` ni sinab bo'lmaydi (hammasi bitta transactionda)
 *   - parallel process yozgan ma'lumot ko'rinmaydi (izolyatsiya)
 *
 * O'rniga: `migrate:fresh` + N ta HAQIQIY PHP PROCESS (`proc_open`), har
 * biri o'z MySQL ulanishi bilan. HTTP server ishlatilmaydi — PHP'ning
 * o'rnatilgan serveri bu muhitda so'rovlarni KETMA-KET bajaradi
 * (`PHP_CLI_SERVER_WORKERS` ta'sir qilmadi), shuning uchun u orqali
 * o'tkazilgan "concurrency" testi hech nimani isbotlamas edi.
 *
 * ⚠️ Bu testlar ODATIY `php artisan test` da YURMAYDI: ular `migrate:fresh`
 * qiladi (bazani tozalaydi) va sekin. Ataylab `concurrency` guruhida,
 * phpunit.xml ularni chiqarib tashlaydi.
 *
 * Ishga tushirish:
 *     php artisan test --group=concurrency
 */
#[Group('concurrency')]
class SessionConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();

        // Transaction YO'Q — ma'lumot haqiqatan DB'da qolishi kerak,
        // aks holda parallel process uni ko'rmaydi.
        Artisan::call('migrate:fresh', ['--force' => true]);

        RestaurantContext::allowCrossRestaurant();
        $restaurant = Restaurant::factory()->create(['slug' => 'concurrency']);
        $this->table = Table::create([
            'restaurant_id' => $restaurant->id,
            'number' => 1,
            'capacity' => 4,
            'nfc_token' => str_repeat('c', 64),
        ]);
        RestaurantContext::reset();

        // Ikki himoya qatlamini ALOHIDA sinash uchun (docs/07 §6).
        // Bu bayroq bilan DB himoyasi olib tashlanadi va faqat
        // `lockForUpdate()` qoladi:
        //
        //     SR_DROP_ACTIVE_KEY_INDEX=1 php artisan test --testsuite=Concurrency
        //
        // Ikkalasi ham o'chirilsa test YIQILADI — shu bilan uning
        // haqiqatan ishlashi tekshirilgan.
        if (env('SR_DROP_ACTIVE_KEY_INDEX')) {
            DB::statement('ALTER TABLE table_sessions DROP INDEX table_sessions_active_key_unique');
        }
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_simultaneous_requests_create_exactly_one_session(): void
    {
        $results = $this->openSessionInParallel();

        foreach ($results as $index => $result) {
            $this->assertTrue(
                $result['ok'],
                "Worker #{$index} xato berdi: ".($result['error'] ?? '?')
            );
        }

        // ENG MUHIM: DB'da AYNAN BITTA session.
        $sessions = DB::table('table_sessions')->where('table_id', $this->table->id)->get();

        $this->assertCount(1, $sessions, 'Bitta stolda bir nechta session yaratildi.');
        $this->assertSame(SessionStatus::ACTIVE->value, $sessions->first()->status);

        // Aynan bitta worker sessionni YARATGAN, qolganlari ULANGAN.
        $created = array_filter($results, static fn (array $r): bool => $r['created'] === true);
        $this->assertCount(1, $created, 'Bir nechta worker o\'zini yaratuvchi deb hisobladi.');

        // Hammasi BITTA sessionni ko'rdi.
        $publicIds = array_unique(array_column($results, 'public_id'));
        $this->assertCount(1, $publicIds, 'Turli sessionlar qaytdi.');

        // Har bir qurilma O'Z tokenini oldi (javob 9).
        $tokens = array_unique(array_column($results, 'token'));
        $this->assertCount(self::WORKERS, $tokens, 'Tokenlar takrorlandi.');

        $this->assertSame(
            self::WORKERS,
            DB::table('session_devices')->count(),
            'Har bir worker uchun bitta qurilma yozuvi bo\'lishi kerak.'
        );
    }

    public function test_the_generated_column_is_the_last_line_of_defence(): void
    {
        // Lock chetlab o'tilsa ham DB ikkinchi ochiq sessionni rad etadi
        // (docs/07-DB-DECISIONS.md §6).
        $this->insertSession(SessionStatus::ACTIVE, 'first');

        $this->expectException(QueryException::class);
        $this->insertSession(SessionStatus::ACTIVE, 'second');
    }

    public function test_a_closed_session_does_not_block_a_new_one(): void
    {
        $this->insertSession(SessionStatus::CLOSED, 'closed-1');
        $this->insertSession(SessionStatus::CLOSED, 'closed-2');
        $this->insertSession(SessionStatus::ACTIVE, 'active');

        $this->assertSame(3, DB::table('table_sessions')->count());
    }

    private function insertSession(SessionStatus $status, string $publicId): void
    {
        DB::table('table_sessions')->insert([
            'restaurant_id' => $this->table->restaurant_id,
            'table_id' => $this->table->id,
            'guest_count' => 2,
            'status' => $status->value,
            'public_id' => $publicId,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * N ta alohida PHP process'ni bir vaqtda ishga tushiradi.
     *
     * @return list<array{ok: bool, created?: bool, public_id?: string, token?: string, error?: string}>
     */
    private function openSessionInParallel(): array
    {
        $worker = __DIR__.'/open_session_worker.php';
        // Barrier: hamma process AYNAN shu vaqtda urinadi. Bo'lmasa
        // birinchisi ulgurib bo'ladi va raqobat yuz bermaydi.
        $startAt = microtime(true) + 1.5;

        $processes = [];
        $pipes = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $command = [
                PHP_BINARY,
                $worker,
                $this->table->nfc_token,
                (string) (2 + $i),
                (string) $startAt,
            ];

            $process = proc_open(
                $command,
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $workerPipes,
                base_path(),
                // Worker TEST bazasiga ulanishi kerak — phpunit.xml
                // qiymati faqat shu process uchun, bola process uni
                // ko'rmaydi, shuning uchun aniq uzatamiz.
                ['DB_DATABASE' => config('database.connections.mysql.database')] + getenv(),
            );

            $this->assertIsResource($process, 'Worker process ishga tushmadi.');

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
