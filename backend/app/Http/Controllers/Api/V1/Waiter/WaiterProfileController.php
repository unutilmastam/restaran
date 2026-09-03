<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Enums\OrderStatus;
use App\Enums\WaiterStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WaiterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaiterProfileController extends Controller
{
    public function __construct(private readonly WaiterService $waiters) {}

    public function show(Request $request): JsonResponse
    {
        $waiter = $request->user();

        return ApiResponse::success([
            'waiter' => $this->present($waiter),
            'today' => [
                'delivered' => Order::query()
                    ->where('waiter_id', $waiter->id)
                    ->where('status', OrderStatus::DELIVERED)
                    ->whereDate('delivered_at', now()->toDateString())
                    ->count(),
                'active' => Order::query()->where('waiter_id', $waiter->id)->open()->count(),
            ],
        ]);
    }

    /** FREE yoki OFFLINE. `BUSY` ni tizim qo'yadi (docs/01 §3). */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                WaiterStatus::FREE->value,
                WaiterStatus::OFFLINE->value,
            ])],
        ]);

        $waiter = $this->waiters->setAvailability(
            $request->user(),
            WaiterStatus::from($validated['status']),
        );

        return ApiResponse::success(['waiter' => $this->present($waiter)]);
    }

    /** @return array<string, mixed> */
    private function present(\App\Models\User $waiter): array
    {
        return [
            'id' => $waiter->id,
            'name' => $waiter->name,
            'username' => $waiter->username,
            'phone' => $waiter->phone,
            'status' => $waiter->status,
            'locale' => $waiter->locale,
            'restaurant' => $waiter->restaurant === null ? null : [
                'name' => $waiter->restaurant->name,
                'currency' => $waiter->restaurant->currency,
            ],
        ];
    }
}
