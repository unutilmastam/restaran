<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * ⚠️ Sanctum tokenni yechayotganda `User` global scope'i CHETLAB
 * O'TILISHI kerak.
 *
 * Sabab: token tekshirilayotgan paytda `RestaurantContext` hali BO'SH —
 * uni `EnsureRole` middleware auth'dan KEYIN o'rnatadi. Scope esa
 * kontekstsiz `whereRaw('1 = 0')` qaytaradi (docs/07-DB-DECISIONS.md §2),
 * shuning uchun tokenning egasi topilmay har bir so'rov 401 berardi.
 *
 * Bu xavfsizlikni zaiflashtirmaydi: token allaqachon o'z egasiga
 * bog'langan, biz faqat O'SHA yozuvni o'qiymiz. Restoran izolyatsiyasi
 * keyingi qadamda — `EnsureRole` kontekstni o'rnatgach — kuchga kiradi.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScopes();
    }
}
