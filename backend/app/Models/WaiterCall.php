<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaiterCallStatus;
use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** docs/01-ARCHITECTURE.md §5 + PHASE 11. */
class WaiterCall extends Model
{
    /** @use HasFactory<\Database\Factories\WaiterCallFactory> */
    use BelongsToRestaurant, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => WaiterCallStatus::class,
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'session_id');
    }

    public function assignedWaiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_waiter_id');
    }
}
