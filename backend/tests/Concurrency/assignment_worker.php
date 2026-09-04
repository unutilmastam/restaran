<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Assignment concurrency worker — ALOHIDA PHP PROCESS
|--------------------------------------------------------------------------
| Argumentlar: <order_id> <start_at>
| Chiqish:     bitta qator JSON
|
| Har process O'Z MySQL ulanishini oladi — ya'ni bu HAQIQIY ikki admin
| bir vaqtda ACCEPT bosgan holat (docs/01-ARCHITECTURE.md §7).
*/

use App\Models\Order;
use App\Services\WaiterAssignmentService;
use App\Support\RestaurantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$orderId, $startAt] = [(int) $argv[1], (float) $argv[2]];

$sleep = $startAt - microtime(true);

if ($sleep > 0) {
    usleep((int) ($sleep * 1_000_000));
}

try {
    RestaurantContext::allowCrossRestaurant();
    $order = Order::query()->findOrFail($orderId);
    RestaurantContext::set($order->restaurant_id);

    $assigned = app(WaiterAssignmentService::class)->acceptAndAssign($order);

    echo json_encode([
        'ok' => true,
        'id' => $assigned->id,
        'status' => $assigned->status->value,
        'waiter_id' => $assigned->waiter_id,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception::class.': '.$exception->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
