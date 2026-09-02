<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\LimitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ImageService $images,
        private readonly LimitService $limits,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return ApiResponse::success(['items' => $products]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        // docs/06-SAAS.md §8 — limit RESTORANGA biriktirilgan.
        $this->limits->assertCanAddProduct($request->user()->restaurant);

        $product = Product::create($request->validated());

        return ApiResponse::success(['product' => $product], null, 201);
    }

    public function update(ProductRequest $request, int $product): JsonResponse
    {
        $model = Product::query()->findOrFail($product);
        $model->update($request->validated());

        return ApiResponse::success(['product' => $model->refresh()]);
    }

    /** Vaqtincha tugagan/paydo bo'lgan mahsulot — tez almashtirish. */
    public function availability(Request $request, int $product): JsonResponse
    {
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);

        $model = Product::query()->findOrFail($product);
        $model->update($validated);

        return ApiResponse::success(['product' => $model->refresh()]);
    }

    /** Rasm: webp + resize, DB'da faqat yo'l (docs/01 §13). */
    public function image(Request $request, int $product): JsonResponse
    {
        $request->validate(['image' => ['required', 'file']]);

        $model = Product::query()->findOrFail($product);

        $path = $this->images->storeProductImage(
            $request->file('image'),
            $model->restaurant_id,
            $model->image,
        );

        $model->update(['image' => $path]);

        return ApiResponse::success([
            'product' => $model->refresh(),
            'url' => asset('storage/'.$path),
        ]);
    }

    public function destroy(int $product): JsonResponse
    {
        $model = Product::query()->findOrFail($product);

        // Rasm ham o'chadi — 1 GB disk byudjeti.
        $this->images->delete($model->image);
        $model->delete();

        return ApiResponse::success(null);
    }
}
