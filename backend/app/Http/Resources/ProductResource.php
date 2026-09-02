<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `price` SON sifatida qaytadi, string emas — `App\Casts\Money`
 * (docs/07-DB-DECISIONS.md §5).
 *
 * ⚠️ Mijoz bu narxni order submit'da QAYTA YUBORMAYDI. Narx har doim
 * DB'dan qayta hisoblanadi (CLAUDE.md §2.6) — bu faqat ko'rsatish uchun.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name(),
            'description' => app()->getLocale() === 'ru'
                ? $this->description_ru
                : $this->description_uz,
            // null bo'lsa frontend placeholder ko'rsatadi.
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'price' => $this->price,
            'discount' => $this->discount,
            'effective_price' => $this->effectivePrice(),
            'weight' => $this->weight,
            'preparation_time' => $this->preparation_time,
            'is_available' => $this->is_available,
        ];
    }
}
