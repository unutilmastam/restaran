<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\WaiterAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin buyurtmalar. DRAFT bu yerda HECH QACHON ko'rinmaydi —
 * `visible()` scope'i (docs/05-PHASE0-PLAN.md §2.4).
 */
class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly WaiterAssignmentService $assignment,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->visible()
            ->with(['items', 'table:id,number', 'waiter:id,name'])
            // `?status=PENDING,WAITING_FOR_WAITER` — admin ekranida yangi
            // buyurtmalar va navbat bitta so'rovda olinadi.
            ->when(
                $request->query('status'),
                fn ($query, string $status) => $query->whereIn(
                    'status',
                    array_filter(array_map('trim', explode(',', $status))),
                ),
            )
            ->when($request->query('table_id'), fn ($query, $id) => $query->where('table_id', $id))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success([
            'items' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(int $order): JsonResponse
    {
        $model = Order::query()->visible()->with(['items', 'table:id,number'])->findOrFail($order);

        return ApiResponse::success(['order' => new OrderResource($model)]);
    }

    /**
     * PENDING → ACCEPTED → biriktirish. Bitta transaction
     * (docs/01-ARCHITECTURE.md §7): admin bitta tugma bosadi, order
     * darhol ASSIGNED yoki WAITING_FOR_WAITER bo'ladi.
     */
    public function accept(int $order): JsonResponse
    {
        $model = Order::query()->visible()->findOrFail($order);

        return ApiResponse::success([
            'order' => new OrderResource(
                $this->assignment->acceptAndAssign($model)
                    ->load(['items', 'table:id,number', 'waiter:id,name']),
            ),
        ]);
    }

    public function cancel(int $order): JsonResponse
    {
        $model = Order::query()->visible()->findOrFail($order);

        return ApiResponse::success([
            'order' => new OrderResource(
                $this->orders->changeStatus($model, OrderStatus::CANCELLED)->load('items'),
            ),
        ]);
    }
}
