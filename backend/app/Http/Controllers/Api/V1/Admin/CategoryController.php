<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Admin panelda IKKALA til ham ko'rinadi (tahrirlash uchun).
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name_uz', 'name_ru', 'slug', 'image', 'sort_order', 'is_active']);

        return ApiResponse::success(['items' => $categories]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        // `restaurant_id` global scope orqali avtomatik to'ladi.
        $category = Category::create($request->validated());

        return ApiResponse::success(['category' => $category], null, 201);
    }

    public function update(CategoryRequest $request, int $category): JsonResponse
    {
        $model = Category::query()->findOrFail($category);
        $model->update($request->validated());

        return ApiResponse::success(['category' => $model->refresh()]);
    }

    public function destroy(int $category): JsonResponse
    {
        // SoftDelete — mahsulotlar va buyurtma tarixi saqlanadi.
        Category::query()->findOrFail($category)->delete();

        return ApiResponse::success(null);
    }
}
