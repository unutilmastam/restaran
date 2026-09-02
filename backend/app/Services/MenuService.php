<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menyuni o'qish. Business logic Controller ichida emas (CLAUDE.md §3.7).
 *
 * Restoran `RestaurantContext` orqali aniqlanadi — global scope o'zi
 * cheklaydi, bu yerda `restaurant_id` bo'yicha qo'lda filtr yo'q
 * (docs/07-DB-DECISIONS.md §2).
 */
class MenuService
{
    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            // Bo'sh kategoriya menyuda ko'rinmaydi — restoran egasi
            // kategoriya qo'shib, mahsulot qo'shmagan bo'lishi mumkin.
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * `is_available = false` mahsulot ham QAYTADI — menyuda "Mavjud emas"
     * deb ko'rsatiladi, lekin cartga qo'shilmaydi (docs/03-PHASES.md PHASE 3).
     * Buyurtma paytidagi haqiqiy tekshiruv PHASE 5 da, serverda.
     *
     * @return Collection<int, Product>
     */
    public function products(?string $search = null): Collection
    {
        return Product::query()
            // Kategoriya bo'yicha guruhlangan tartib: "hammasi" ko'rinishida
            // mahsulotlar aralashib ketmasin. `products.sort_order` har
            // kategoriya ichida 1 dan boshlanadi, shuning uchun avval
            // kategoriya tartibi kerak.
            ->select('products.*')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                // Ikkala tilda ham qidiriladi: mijoz UZ interfeysda
                // "плов" deb yozishi mumkin (docs/02 §3).
                $query->where(function ($inner) use ($like): void {
                    $inner->where('products.name_uz', 'like', $like)
                        ->orWhere('products.name_ru', 'like', $like);
                });
            })
            ->orderBy('categories.sort_order')
            ->orderBy('products.sort_order')
            ->orderBy('products.id')
            ->get();
    }
}
