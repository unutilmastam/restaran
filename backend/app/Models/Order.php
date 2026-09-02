<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** docs/01-ARCHITECTURE.md §5, §8. */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use BelongsToRestaurant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'guest_count' => 'integer',
            'subtotal' => Money::class,
            'discount' => Money::class,
            'total' => Money::class,
            'business_date' => 'date',
            'draft_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'waiter_accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'session_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * DRAFT hech qanday ro'yxatda ko'rinmaydi, broadcast qilinmaydi va
     * waiter'ga assign qilinmaydi (docs/05-PHASE0-PLAN.md §2.4).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', OrderStatus::DRAFT->value);
    }

    /** "Yetkazilmagan order bor" tekshiruvi (CLAUDE.md §2.4). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (OrderStatus $status): string => $status->value,
            array_filter(OrderStatus::cases(), static fn (OrderStatus $s): bool => $s->isOpen()),
        ));
    }
}
