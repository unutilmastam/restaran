<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminTableController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Customer\MenuController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\SessionController;
use App\Http\Controllers\Api\V1\Customer\TableController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\RequireCustomerSession;
use App\Http\Middleware\ResolveCustomer;
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
Route::middleware(ResolveCustomer::class)->group(function (): void {
    // Haqiqiy session talab qiladi — draft tokeni o'ta olmaydi.
    Route::get('/sessions/me', [SessionController::class, 'me'])
        ->middleware(RequireCustomerSession::class);

    // Draft tokeni ham ishlaydi: mijoz o'z draftini kuzata olishi kerak.
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
});

/*
|--------------------------------------------------------------------------
| AUTH (staff) — docs/05-PHASE0-PLAN.md §3.3
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/locale', [AuthController::class, 'locale']);
});

/*
|--------------------------------------------------------------------------
| ADMIN — docs/03-PHASES.md PHASE 6
|--------------------------------------------------------------------------
| ⚠️ SUPER_ADMIN bu yerga KIRMAYDI: uning restaurant_id = null va global
| scope hech nima qaytarmaydi. Platforma boshqaruvi /super/* da (PHASE 13.5).
*/
Route::middleware(['auth:sanctum', 'role:OWNER_ADMIN,ADMIN'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
        Route::post('/orders/{order}/accept', [AdminOrderController::class, 'accept'])->whereNumber('order');
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->whereNumber('order');

        Route::apiResource('categories', CategoryController::class)->except('show');

        Route::apiResource('products', ProductController::class)->except('show');
        Route::patch('/products/{product}/availability', [ProductController::class, 'availability'])->whereNumber('product');
        Route::post('/products/{product}/image', [ProductController::class, 'image'])->whereNumber('product');

        Route::apiResource('tables', AdminTableController::class)->except('show');
        Route::post('/tables/{table}/regenerate-token', [AdminTableController::class, 'regenerateToken'])->whereNumber('table');

        Route::apiResource('staff', StaffController::class)->except('show');
    });
