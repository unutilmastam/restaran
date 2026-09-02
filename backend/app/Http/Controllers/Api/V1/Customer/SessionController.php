<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpenSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\SessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/03-PHASES.md PHASE 4.
 *
 *   POST /api/v1/t/{nfc_token}/sessions  → session ochish yoki ulanish
 *   GET  /api/v1/sessions/me             → joriy session (X-Customer-Token)
 */
class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function store(OpenSessionRequest $request): JsonResponse
    {
        /** @var Table $table */
        $table = $request->attributes->get('table');

        if (! $table->restaurant->subscription_status->isOperational()) {
            throw new BusinessException('RESTAURANT_UNAVAILABLE', 403);
        }

        $result = $this->sessions->openSession($table, (int) $request->validated('guest_count'));

        return ApiResponse::success(
            [
                // ⚠️ Plaintext token FAQAT SHU YERDA, bir marta qaytadi.
                // Keyin DB'da faqat hash qoladi (docs/05 §2.3).
                'customer_token' => $result['token'],
                'session' => new SessionResource($result['session']),
            ],
            null,
            // Mavjud sessionga ulanish — yangi resurs emas, 200.
            $result['created'] ? 201 : 200,
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('session');

        return ApiResponse::success([
            'session' => new SessionResource($session),
            'table' => [
                'number' => $session->table->number,
                'name' => $session->table->name,
            ],
            // Orderlar PHASE 5 da qo'shiladi.
            'orders' => [],
        ]);
    }
}
