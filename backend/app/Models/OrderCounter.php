<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

/** docs/05-PHASE0-PLAN.md §2.5 — OrderNumberService lockForUpdate() bilan oladi. */
class OrderCounter extends Model
{
    use BelongsToRestaurant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'last_number' => 'integer',
        ];
    }
}
