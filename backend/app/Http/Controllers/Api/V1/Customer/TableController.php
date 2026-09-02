<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantResource;
use App\Http\Resources\TableResource;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/t/{nfc_token}
 * GET /api/v1/r/{slug}/t/{nfc_token}   (docs/06-SAAS.md §7)
 *
 * NFC skanerlanganda birinchi so'rov. Stol topilmasa middleware
 * `INVALID_TABLE` beradi.
 */
class TableController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Table $table */
        $table = $request->attributes->get('table');
        $restaurant = $table->restaurant;

        // docs/06-SAAS.md §4 — obuna tugagan bo'lsa sahifa OCHILADI,
        // lekin menyu ko'rinmaydi. Bloklash sababi mijozga tushunarli
        // matn bilan qaytadi; sessiya va buyurtma oqimi PHASE 4-5 da.
        $available = $restaurant->subscription_status->isOperational();

        return \App\Support\ApiResponse::success([
            'restaurant' => new RestaurantResource($restaurant),
            'table' => new TableResource($table),
            'is_available' => $available,
            'blocked_reason' => $available ? null : 'RESTAURANT_UNAVAILABLE',
            // Session oqimi PHASE 4 da qo'shiladi.
            'session' => null,
        ]);
    }
}
