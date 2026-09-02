<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ⚠️ `id` va `restaurant_id` QAYTMAYDI — mijozga faqat `public_id`
 * ko'rinadi (docs/01-ARCHITECTURE.md §13). Predictable `session_id=501`
 * kabi qiymat hech qayerda ishlatilmaydi (docs/03-PHASES.md PHASE 4).
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'status' => $this->status,
            'guest_count' => $this->guest_count,
            'total_amount' => $this->total_amount,
            'opened_at' => $this->opened_at?->toIso8601String(),
            // `false` bo'lsa mijoz menyuni ko'radi, lekin buyurtma
            // yubora olmaydi (docs/01 §12).
            'can_order' => $this->status === \App\Enums\SessionStatus::ACTIVE,
        ];
    }
}
