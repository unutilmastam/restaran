<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/01-ARCHITECTURE.md §13 + docs/07-DB-DECISIONS.md §1.
 *
 * `restaurant_id` NULLABLE: SUPER_ADMIN ning platforma darajasidagi
 * amallari (plans, settings) hech qaysi restoranga tegishli emas, va
 * restoran butunlay o'chganda audit yozuvi qolishi kerak.
 */
class ActivityLog extends Model
{
    use BelongsToRestaurant;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function allowsPlatformRows(): bool
    {
        return true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
