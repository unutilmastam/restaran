<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\LimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly LimitService $limits,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurant;

        return ApiResponse::success([
            'today' => $this->dashboard->today($restaurant),
            'tables' => $this->dashboard->tables(),
            'limits' => $this->limits->usage($restaurant),
        ]);
    }
}
