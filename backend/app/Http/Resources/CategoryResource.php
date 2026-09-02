<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nom `Accept-Language` bo'yicha tanlanadi (docs/02-I18N-RU-UZ.md §3) —
 * frontend `name_ru`/`name_uz` bilan ovora bo'lmasin.
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name(),
            'slug' => $this->slug,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
        ];
    }
}
