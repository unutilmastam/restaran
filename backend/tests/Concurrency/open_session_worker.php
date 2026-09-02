<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Concurrency worker — ALOHIDA PHP PROCESS
|--------------------------------------------------------------------------
| `SessionConcurrencyTest` shu skriptni N marta bir vaqtda ishga tushiradi.
|
| Nega alohida process: har biri O'Z MySQL ulanishini oladi, shuning uchun
| `lockForUpdate()` va `UNIQUE(active_key)` haqiqiy raqobatda sinaladi.
| `RefreshDatabase` yoki bitta process ichidagi sikl buni QILA OLMAYDI —
| u yerda hammasi bitta transaction va bitta ulanishda bo'ladi.
|
| Argumentlar:  <nfc_token> <guest_count> <start_at_microtime>
| Chiqish:      bitta qator JSON
*/

use App\Models\Table;
use App\Services\SessionService;
use App\Support\RestaurantContext;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$nfcToken, $guestCount, $startAt] = [$argv[1], (int) $argv[2], (float) $argv[3]];

// To'siq (barrier): hamma worker AYNAN bir vaqtda urinadi — aks holda
// birinchisi ulgurib bo'ladi va raqobat umuman yuz bermaydi.
$sleep = $startAt - microtime(true);

if ($sleep > 0) {
    usleep((int) ($sleep * 1_000_000));
}

try {
    RestaurantContext::allowCrossRestaurant();

    $table = Table::where('nfc_token', $nfcToken)->firstOrFail();

    RestaurantContext::set($table->restaurant_id);

    $result = app(SessionService::class)->openSession($table, $guestCount);

    echo json_encode([
        'ok' => true,
        'created' => $result['created'],
        'public_id' => $result['session']->public_id,
        'token' => $result['token'],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class.': '.$exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
