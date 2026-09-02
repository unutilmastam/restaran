<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer uchun restoran ma'lumoti.
 *
 * ⚠️ `id`, `expires_at`, limitlar va egasining aloqasi QAYTMAYDI
 * (docs/01-ARCHITECTURE.md §13).
 */
class RestaurantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logo ? asset('storage/'.$this->logo) : null,
            'currency' => $this->currency,
            'default_locale' => $this->default_locale,
        ];
    }
}
