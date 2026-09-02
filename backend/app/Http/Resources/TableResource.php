<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ⚠️ `id`, `restaurant_id`, `nfc_token`, `nfc_uid` QAYTMAYDI —
 * mijoz ularni bilishi shart emas (docs/01-ARCHITECTURE.md §13).
 */
class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'status' => $this->status,
        ];
    }
}
