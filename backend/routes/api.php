<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Customer\MenuController;
use App\Http\Controllers\Api\V1\Customer\TableController;
use App\Http\Controllers\Api\V1\HealthController;
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
    });
}

// Order yuborish PHASE 5 da, session PHASE 4 da.
