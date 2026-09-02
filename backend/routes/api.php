<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Prefiks `api/v1` bootstrap/app.php da apiPrefix orqali beriladi.
| To'liq contract: docs/05-PHASE0-PLAN.md §3.
|
| PHASE 1 da faqat /health mavjud. Qolgan endpointlar o'z phase'larida
| qo'shiladi:
|   customer  → PHASE 3-5   waiter → PHASE 7
|   auth      → PHASE 6     admin  → PHASE 6, 12, 13
*/

Route::get('/health', HealthController::class)->name('health');
