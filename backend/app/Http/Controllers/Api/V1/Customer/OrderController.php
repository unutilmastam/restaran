<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\SessionStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\OrderService;
use App\Services\SessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/t/{nfc_token}/orders
 * GET  /api/v1/orders/{order}      (X-Customer-Token)
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly SessionService $sessions,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        /** @var Table $table */
        $table = $request->attributes->get('table');

        if (! $table->restaurant->subscription_status->isOperational()) {
            throw new BusinessException('RESTAURANT_UNAVAILABLE', 403);
        }

        $uuid = (string) $request->validated('client_order_uuid');

        // Idempotency: shu uuid bilan order bo'lsa 200 va O'SHA order
        // qaytadi — yangi yaratilmaydi (CLAUDE.md §3.1).
        $existing = $this->orders->findByClientUuid($table->restaurant_id, $uuid);

        if ($existing !== null) {
            return $this->respond($existing, 200);
        }

        $session = $this->sessions->findActiveSession($table);

        // Mijozning tokeni bo'lmasa (WAITING_PAYMENT stolda session
        // ochilmaydi) draft uchun yangisi beriladi.
        $customerToken = $request->header('X-Customer-Token');
        $issuedToken = null;

        if (! is_string($customerToken) || $customerToken === '') {
            $customerToken = null;
        }

        if ($session === null || $session->status !== SessionStatus::ACTIVE) {
            $issuedToken = $customerToken ?? $this->sessions->issueDraftToken();
            $customerToken = $issuedToken;
        }

        $order = $this->orders->createOrder(
            $table,
            $uuid,
            $request->lines(),
            $session,
            $request->validated('note'),
            $customerToken,
        );

        // WAITING_PAYMENT holatida order DRAFT bo'lib saqlandi —
        // mijozga 409 va tushuntirish qaytadi (docs/01 §12, 18-qadam).
        if ($order->status->isDraft()) {
            return ApiResponse::error('SESSION_WAITING_PAYMENT', 409, [
                'draft_order_id' => $order->id,
                // ⚠️ Bu token draftning YAGONA kaliti — mijoz uni
                // saqlashi kerak, aks holda o'z buyurtmasini yo'qotadi.
                'customer_token' => $issuedToken,
                'order' => new OrderResource($order->load('items')),
            ]);
        }

        return $this->respond($order, 201);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        /** @var TableSession|null $session */
        $session = $request->attributes->get('session');
        $tokenHash = (string) $request->attributes->get('customer_token_hash');

        /*
         * Ko'rish huquqi ikki yo'l bilan beriladi:
         *
         *   1. Order mijozning SESSIONIGA tegishli
         *   2. Order — mijozning O'Z DRAFTI (token hash mos keladi)
         *
         * ⚠️ "Shu stoldagi har qanday draft" qoidasi ATAYIN YO'Q:
         * bitta stolda ikki telefon draft qoldirsa, har biri
         * ikkinchisining buyurtmasini ko'rardi.
         */
        $model = Order::withoutGlobalScopes()
            ->with('items')
            ->where('id', $order)
            ->where(function ($query) use ($session, $tokenHash): void {
                $query->where('created_by_token_hash', $tokenHash);

                if ($session !== null) {
                    $query->orWhere('session_id', $session->id);
                }
            })
            ->first();

        if ($model === null) {
            throw new BusinessException('NOT_FOUND', 404);
        }

        return $this->respond($model, 200);
    }

    private function respond(Order $order, int $status): JsonResponse
    {
        return ApiResponse::success(
            ['order' => new OrderResource($order->load('items'))],
            null,
            $status,
        );
    }
}
