<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Waiter PWA push (PHASE 7). `User` ning bolasi — `restaurant_id` yo'q. */
class PushSubscription extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['public_key', 'auth_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
