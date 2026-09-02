<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/06-SAAS.md §2 — PLATFORMA darajasi, `restaurant_id` yo'q,
 * shuning uchun `BelongsToRestaurant` qo'llanmaydi.
 *
 * ⚠️ To'lov tarixi bu modelga JOIN QILMAYDI. Summa har doim
 * `SubscriptionPayment` ning snapshot ustunlaridan olinadi
 * (docs/07-DB-DECISIONS.md §3).
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => Money::class,
            'days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? $this->name_ru : $this->name_uz;
    }
}
