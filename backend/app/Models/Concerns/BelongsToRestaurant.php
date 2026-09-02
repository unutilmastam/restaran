<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Restaurant;
use App\Models\Scopes\RestaurantScope;
use App\Support\RestaurantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/07-DB-DECISIONS.md §2 — 14 ta modelga qo'llanadi.
 *
 * Ikki ish qiladi:
 *   1. Global scope — o'qishda restoran bo'yicha cheklaydi
 *   2. `creating` — yozishda `restaurant_id` ni AVTOMATIK to'ldiradi
 *
 * 2-qadam muhim: `restaurant_id` HECH QACHON request'dan olinmaydi
 * (docs/06-SAAS.md §10.2), faqat kontekstdan.
 */
trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new RestaurantScope(
            static::allowsPlatformRows(),
        ));

        static::creating(function ($model): void {
            if ($model->restaurant_id === null && ! RestaurantContext::isUnscoped()) {
                $model->restaurant_id = RestaurantContext::get();
            }
        });
    }

    /**
     * `restaurant_id IS NULL` yozuvlari ham ko'rinsinmi.
     * Faqat `Setting` `true` qaytaradi (docs/07 §2) — boshqa modelga
     * tarqalmasligini PHASE 14 testi qulflaydi.
     */
    protected static function allowsPlatformRows(): bool
    {
        return false;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
