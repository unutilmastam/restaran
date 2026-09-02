<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Models\Scopes\RestaurantScope;
use App\Support\RestaurantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * docs/06-SAAS.md §1 — 4 ta rol. `restaurant_id = null` → SUPER_ADMIN.
 *
 * `BelongsToRestaurant` trait ISHLATILMAYDI: bu yerda scope
 * `allowPlatformRows = false` bilan qo'lda qo'shiladi, shunda restoran
 * admini SUPER_ADMIN hisobini KO'RMAYDI (docs/07-DB-DECISIONS.md §2).
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin' => 'hashed',
            'role' => UserRole::class,
            'status' => WaiterStatus::class,
            'last_free_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope);

        static::creating(function (User $user): void {
            if ($user->restaurant_id === null
                && $user->role !== UserRole::SUPER_ADMIN
                && ! RestaurantContext::isUnscoped()) {
                $user->restaurant_id = RestaurantContext::get();
            }
        });

        // docs/06-SAAS.md §1 — OWNER_ADMIN ni o'chirib bo'lmaydi.
        // DB da generated column faqat IKKINCHISINI to'sadi, o'chirishni emas.
        static::deleting(function (User $user): void {
            if ($user->role === UserRole::OWNER_ADMIN && ! RestaurantContext::isUnscoped()) {
                throw new \App\Exceptions\BusinessException('OWNER_ADMIN_PROTECTED', 403);
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isWaiter(): bool
    {
        return $this->role === UserRole::WAITER;
    }
}
