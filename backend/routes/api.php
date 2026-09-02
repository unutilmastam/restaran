<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Customer\MenuController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\SessionController;
use App\Http\Controllers\Api\V1\Customer\TableController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\ResolveCustomerSession;
use App\Http\Middleware\ResolveTableByNfcToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Prefiks `api/v1` bootstrap/app.php da apiPrefix orqali beriladi.
| To'liq contract: docs/05-PHASE0-PLAN.md §3.
*/

Route::get('/health', HealthController::class)->name('health');

/*
| CUSTOMER (guest) — auth yo'q, restoran nfc_token orqali aniqlanadi.
|
| Ikki shakl (docs/06-SAAS.md §7):
|   /t/{nfc_token}            — qisqa, NFC tagga yoziladi
|   /r/{slug}/t/{nfc_token}   — restoran nomi ko'rinadigan URL
*/
foreach (['/t/{nfc_token}', '/r/{slug}/t/{nfc_token}'] as $prefix) {
    Route::middleware(ResolveTableByNfcToken::class)->group(function () use ($prefix): void {
        Route::get($prefix, TableController::class);
        Route::get($prefix.'/menu', MenuController::class);

        // Session ochish yoki mavjudiga ulanish (docs/01 §12).
        // Rate limit: bir stolga daqiqasiga 10 ta so'rov (docs/05 §3.7).
        Route::post($prefix.'/sessions', [SessionController::class, 'store'])
            ->middleware('throttle:10,1');

        // Order submit (docs/01-ARCHITECTURE.md §8).
        // Rate limit: 10/daqiqa per stol (docs/05-PHASE0-PLAN.md §3.7).
        Route::post($prefix.'/orders', [OrderController::class, 'store'])
            ->middleware('throttle:10,1');
    });
}

/*
| Session'ga bog'langan chaqiruvlar — `X-Customer-Token` header'i bilan.
| Bu yerda nfc_token yo'q, restoran token orqali aniqlanadi.
*/
Route::middleware(ResolveCustomerSession::class)->group(function (): void {
    Route::get('/sessions/me', [SessionController::class, 'me']);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
});
