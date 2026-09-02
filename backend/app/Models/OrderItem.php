<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CLAUDE.md §3.3 — nom va narx SNAPSHOT.
 * `restaurant_id` yo'q: `Order` ning bolasi (docs/07-DB-DECISIONS.md §1).
 */
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price_snapshot' => Money::class,
            'subtotal' => Money::class,
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Nom `products` dan EMAS, snapshotdan olinadi. */
    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru'
            ? $this->product_name_ru_snapshot
            : $this->product_name_uz_snapshot;
    }
}
