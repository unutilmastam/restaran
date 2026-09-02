<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** docs/01-ARCHITECTURE.md §5. Narx HAR DOIM shu yerdan olinadi (CLAUDE.md §2.6). */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToRestaurant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => Money::class,
            // FOIZ (0-100), summa emas — javob 6.
            'discount' => 'integer',
            'weight' => 'integer',
            'preparation_time' => 'integer',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? $this->name_ru : $this->name_uz;
    }

    /** Menyuda ko'rinadigan va buyurtma qilinadigan mahsulotlar. */
    public function scopeOrderable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_available', true);
    }

    /** Chegirma FOIZ sifatida qo'llanadi (javob 6). */
    public function effectivePrice(): float
    {
        return round($this->price * (100 - $this->discount) / 100, 2);
    }
}
