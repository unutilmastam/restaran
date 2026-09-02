<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Table;
use App\Services\MenuService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/t/{nfc_token}/menu
 * GET /api/v1/r/{slug}/t/{nfc_token}/menu
 *
 * Kategoriya va mahsulotlar `Accept-Language` bo'yicha bitta `name`
 * maydoni bilan qaytadi (docs/02-I18N-RU-UZ.md §3).
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menu) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var Table $table */
        $table = $request->attributes->get('table');

        // docs/06-SAAS.md §4 — obuna tugagan restoranda menyu OCHILMAYDI.
        if (! $table->restaurant->subscription_status->isOperational()) {
            throw new BusinessException('RESTAURANT_UNAVAILABLE', 403);
        }

        $search = $request->query('q');

        return ApiResponse::success([
            'categories' => CategoryResource::collection($this->menu->categories()),
            'products' => ProductResource::collection(
                $this->menu->products(is_string($search) ? trim($search) : null),
            ),
        ]);
    }
}
