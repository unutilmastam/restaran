<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use App\Enums\SubscriptionPaymentMethod;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * docs/06-SAAS.md §2 + docs/07-DB-DECISIONS.md §3 — SNAPSHOT yozuvi.
 *
 * `plans.price` yoki `plans.name_*` o'zgarsa, bu yozuvlar O'ZGARMAYDI.
 * 4 qatlamli kafolatning 2-qatlami shu yerda: yozuv FAQAT YARATILADI,
 * hech qachon tahrirlanmaydi.
 */
class SubscriptionPayment extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPaymentFactory> */
    use BelongsToRestaurant, HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => Money::class,
            'method' => SubscriptionPaymentMethod::class,
            'plan_days_snapshot' => 'integer',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException(
                'To\'lov yozuvi o\'zgartirilmaydi (docs/07-DB-DECISIONS.md §3).'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'To\'lov yozuvi o\'chirilmaydi — moliyaviy tarix.'
            );
        });
    }

    /** Tarif nomi — `plans` dan EMAS, snapshotdan. */
    public function planName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru'
            ? $this->plan_name_ru_snapshot
            : $this->plan_name_uz_snapshot;
    }

    /** Faqat ma'lumot uchun — summa manbai EMAS. */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
