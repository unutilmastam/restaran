<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Multi-tenant izolyatsiya — docs/06-SAAS.md §10, docs/07-DB-DECISIONS.md §2.
 *
 * Mantiq:
 *   1. RestaurantContext::isUnscoped()  → cheklov YO'Q   (faqat /super/*)
 *   2. restaurant_id aniqlandi          → where restaurant_id = X
 *   3. restaurant_id aniqlanmadi        → whereRaw('1 = 0')
 *
 * 3-qadam ataylab shunday: aniqlanmagan holatda cheklovsiz QOLDIRILMAYDI,
 * aksincha hech nima qaytarilmaydi. Kod xatosi bo'lsa sahifa bo'sh qoladi,
 * ma'lumot sizib chiqmaydi.
 *
 * ⚠️ Bu yerda ROL tekshirilmaydi. Agar `role === SUPER_ADMIN` bo'yicha
 * chetlab o'tilsa, SUPER_ADMIN `/admin/*` ga kirganda ham hamma restoranni
 * ko'rardi. Chetlab o'tish faqat `AllowCrossRestaurant` middleware orqali,
 * u esa FAQAT `/api/v1/super/*` guruhiga ulanadi.
 */
class RestaurantScope implements Scope
{
    /**
     * @param  bool  $allowPlatformRows  `settings` uchun: restaurant_id IS NULL
     *                                   bo'lgan platforma yozuvlari ham ko'rinadi
     */
    public function __construct(private readonly bool $allowPlatformRows = false) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (RestaurantContext::isUnscoped()) {
            return;
        }

        $column = $model->qualifyColumn('restaurant_id');
        $restaurantId = RestaurantContext::get();

        if ($restaurantId === null) {
            // Platforma yozuvlari restoran aniqlanmagan holatda ham kerak
            // bo'lishi mumkin (masalan login sahifasidagi aloqa ma'lumoti).
            if ($this->allowPlatformRows) {
                $builder->whereNull($column);

                return;
            }

            $builder->whereRaw('1 = 0');

            return;
        }

        if ($this->allowPlatformRows) {
            $builder->where(function (Builder $query) use ($column, $restaurantId): void {
                $query->where($column, $restaurantId)->orWhereNull($column);
            });

            return;
        }

        $builder->where($column, $restaurantId);
    }
}
