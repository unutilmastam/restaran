<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\WaiterService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/03-PHASES.md PHASE 7.
 *
 * ⚠️ Afitsant FAQAT o'ziga biriktirilgan orderlarni ko'radi va
 * o'zgartiradi. Boshqa afitsantning orderiga urinish → 403.
 */
class WaiterOrderController extends Controller
{
    public function __construct(private readonly WaiterService $waiters) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => OrderResource::collection(
                $this->waiters->activeOrders($request->user()),
            ),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'items' => OrderResource::collection(
                $this->waiters->history($request->user()),
            ),
        ]);
    }

    public function accept(Request $request, int $order): JsonResponse
    {
        return $this->respond(
            $this->waiters->accept($request->user(), $this->find($order)),
        );
    }

    public function delivering(Request $request, int $order): JsonResponse
    {
        return $this->respond(
            $this->waiters->startDelivering($request->user(), $this->find($order)),
        );
    }

    public function deliver(Request $request, int $order): JsonResponse
    {
        return $this->respond(
            $this->waiters->deliver($request->user(), $this->find($order)),
        );
    }

    /**
     * Order o'z restoranida bormi. Global scope allaqachon boshqa
     * restoranni to'sadi; egalik tekshiruvi `WaiterService` da —
     * u yerda 403 beriladi, bu yerda 404.
     */
    private function find(int $order): Order
    {
        $model = Order::query()->visible()->find($order);

        if ($model === null) {
            throw new BusinessException('NOT_FOUND', 404);
        }

        return $model;
    }

    private function respond(Order $order): JsonResponse
    {
        return ApiResponse::success([
            'order' => new OrderResource($order->load(['items', 'table:id,number,name'])),
        ]);
    }
}
