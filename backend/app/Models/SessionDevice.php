<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir stolda bir necha telefon bitta sessionni bo'lishadi (javob 9).
 * `restaurant_id` yo'q — `TableSession` ning bolasi.
 */
class SessionDevice extends Model
{
    /** @use HasFactory<\Database\Factories\SessionDeviceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['customer_token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    /** Token ochiq matnda saqlanmaydi — faqat hash bo'yicha qidiriladi. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
