<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fizik stol — doimiy. NFC tag unga yopishtirilgan.
 * `TableSession` bilan HECH QACHON birlashtirilmaydi (CLAUDE.md §2.3).
 */
class Table extends Model
{
    /** @use HasFactory<\Database\Factories\TableFactory> */
    use BelongsToRestaurant, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['nfc_uid'];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'number' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /** Ochiq session — `active_key` generated column bilan bir xil shart. */
    public function activeSession(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->whereIn('status', array_column(SessionStatus::occupying(), 'value'));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
