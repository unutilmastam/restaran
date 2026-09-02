<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Concurrency worker — ALOHIDA PHP PROCESS
|--------------------------------------------------------------------------
| Argumentlar: <nfc_token> <product_id> <client_order_uuid> <start_at>
| Chiqish:     bitta qator JSON
|
| Har process O'Z MySQL ulanishini oladi — `lockForUpdate()` va
| `UNIQUE(restaurant_id, client_order_uuid)` haqiqiy raqobatda sinaladi.
*/

use App\Models\Table;
use App\Services\OrderService;
use App\Support\RestaurantContext;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$nfcToken, $productId, $uuid, $startAt] = [$argv[1], (int) $argv[2], $argv[3], (float) $argv[4]];

$sleep = $startAt - microtime(true);

if ($sleep > 0) {
    usleep((int) ($sleep * 1_000_000));
}

try {
    RestaurantContext::allowCrossRestaurant();
    $table = Table::where('nfc_token', $nfcToken)->firstOrFail();
    RestaurantContext::set($table->restaurant_id);

    $order = app(OrderService::class)->createOrder(
        $table,
        $uuid,
        [['product_id' => $productId, 'quantity' => 1]],
    );

    echo json_encode([
        'ok' => true,
        'id' => $order->id,
        'order_number' => $order->order_number,
        'status' => $order->status->value,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class.': '.$exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
