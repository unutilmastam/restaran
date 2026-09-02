<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant. `BelongsToRestaurant` QO'LLANMAYDI — o'zi restoran
 * (docs/07-DB-DECISIONS.md §2).
 */
class Restaurant extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'subscription_status' => SubscriptionStatus::class,
            'max_tables' => 'integer',
            'max_products' => 'integer',
            'max_waiters' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /** docs/06-SAAS.md §4 — restoran to'liq ishlay oladimi. */
    public function isOperational(): bool
    {
        return $this->is_active
            && $this->deleted_at === null
            && $this->subscription_status->isOperational();
    }

    /** Manfiy qiymat = necha kun oldin tugagan. */
    public function daysLeft(): int
    {
        return $this->expires_at === null
            ? 0
            : (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false);
    }
}
