<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin buyurtmalar. DRAFT bu yerda HECH QACHON ko'rinmaydi —
 * `visible()` scope'i (docs/05-PHASE0-PLAN.md §2.4).
 */
class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->visible()
            ->with(['items', 'table:id,number', 'waiter:id,name'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
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

    /** PENDING → ACCEPTED. Transaction OrderService ichida. */
    public function accept(int $order): JsonResponse
    {
        $model = Order::query()->visible()->findOrFail($order);

        return ApiResponse::success([
            'order' => new OrderResource(
                $this->orders->changeStatus($model, OrderStatus::ACCEPTED)->load('items'),
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
