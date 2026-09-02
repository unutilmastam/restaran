<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\TableResource;
use App\Models\Table;
use App\Services\SessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/t/{nfc_token}
 * GET /api/v1/r/{slug}/t/{nfc_token}   (docs/06-SAAS.md §7)
 *
 * NFC skanerlanganda birinchi so'rov. Javob mijoz qaysi ekranni
 * ko'rishini belgilaydi — docs/01-ARCHITECTURE.md §12:
 *
 *   session yo'q          → "Necha kishi?" ekrani
 *   ACTIVE                → mavjud sessionga ulanadi, menyu ochiladi
 *   WAITING_PAYMENT       → menyu OCHILADI, lekin buyurtma bloklanadi
 *   restoran obunasi yo'q → "Restoran vaqtincha ishlamayapti"
 */
class TableController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Table $table */
        $table = $request->attributes->get('table');
        $restaurant = $table->restaurant;

        $available = $restaurant->subscription_status->isOperational();
        $session = $available ? $this->sessions->findActiveSession($table) : null;

        return ApiResponse::success([
            'restaurant' => new RestaurantResource($restaurant),
            'table' => new TableResource($table),
            'is_available' => $available,
            'blocked_reason' => $available ? null : 'RESTAURANT_UNAVAILABLE',
            'session' => $session === null ? null : new SessionResource($session),
            // Mijoz buyurtma bera oladimi. WAITING_PAYMENT holatida cart
            // TAYYORLANADI, lekin submit bloklanadi (docs/01 §12) —
            // bloklash PHASE 5 da, serverda.
            'can_order' => $available
                && ($session === null || $session->status === SessionStatus::ACTIVE),
            'order_blocked_reason' => $this->orderBlockedReason($available, $session?->status),
        ]);
    }

    private function orderBlockedReason(bool $available, ?SessionStatus $status): ?string
    {
        if (! $available) {
            return 'RESTAURANT_UNAVAILABLE';
        }

        return $status === SessionStatus::WAITING_PAYMENT ? 'SESSION_WAITING_PAYMENT' : null;
    }
}
