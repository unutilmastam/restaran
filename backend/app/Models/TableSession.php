<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Money;
use App\Enums\SessionStatus;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mijozlar guruhi — vaqtinchalik (docs/01-ARCHITECTURE.md §2). */
class TableSession extends Model
{
    /** @use HasFactory<\Database\Factories\TableSessionFactory> */
    use BelongsToRestaurant, HasFactory;

    protected $guarded = ['id'];

    /** `active_key` — DB hisoblaydi, kod hech qachon yozmaydi. */
    protected $guarded_from_mass_assignment = ['active_key'];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'guest_count' => 'integer',
            'total_amount' => Money::class,
            'paid_amount' => Money::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'session_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(SessionDevice::class);
    }

    public function isOccupying(): bool
    {
        return in_array($this->status, SessionStatus::occupying(), true);
    }
}
