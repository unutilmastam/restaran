<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * cPanel shared hostingda monitoring uchun (docs/05-PHASE0-PLAN.md §3.1).
 *
 * Redis yo'q — cache va queue `database` driverida ishlaydi, shuning uchun
 * ularning sog'ligi ham DB orqali tekshiriladi. `queue_pending` cron
 * to'xtab qolganini ko'rsatadi (queue:work cron bilan yuriladi).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'database' => $this->check(fn () => DB::connection()->getPdo() !== null),
            'cache' => $this->check(function (): bool {
                Cache::put('health:ping', 1, 10);

                return Cache::get('health:ping') === 1;
            }),
        ];

        $checks['queue_pending'] = $this->pendingJobs();
        $healthy = ! in_array('fail', $checks, true);

        return ApiResponse::success([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'version' => config('app.version', '0.1.0-phase1'),
            'time' => now()->toIso8601String(),
        ], null, $healthy ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            return $probe() ? 'ok' : 'fail';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function pendingJobs(): int|string
    {
        try {
            return DB::table('jobs')->count();
        } catch (Throwable) {
            return 'fail';
        }
    }
}
