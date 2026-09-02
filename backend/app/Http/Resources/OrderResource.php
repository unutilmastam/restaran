<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ⚠️ `restaurant_id`, `table_id`, `session_id`, `waiter_id` QAYTMAYDI
 * (docs/01-ARCHITECTURE.md §13).
 *
 * ⚠️ "Kitchen accepted" kabi status YO'Q — oshpaz tizimdan
 * foydalanmaydi (CLAUDE.md §2.1).
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'note' => $this->note,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'name' => $item->name(),
                'price' => $item->price_snapshot,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'note' => $item->note,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            // Frontend polling'ni shu bo'yicha to'xtatadi.
            'is_final' => $this->status->isFinal(),
        ];
    }
}
