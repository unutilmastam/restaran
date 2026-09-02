<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * docs/06-SAAS.md §3 — kunlik scheduler hisoblaydi:
 *
 *   expires_at − bugun > 5 kun   → ACTIVE
 *   expires_at − bugun ≤ 5 kun   → EXPIRING
 *   expires_at < bugun           → EXPIRED
 *   SUPER_ADMIN qo'lda           → SUSPENDED
 *
 * TRIAL — boshlanish usuli. Tugagach oddiy EXPIRED oqimiga o'tadi.
 */
enum SubscriptionStatus: string
{
    case TRIAL = 'TRIAL';
    case ACTIVE = 'ACTIVE';
    case EXPIRING = 'EXPIRING';
    case EXPIRED = 'EXPIRED';
    case SUSPENDED = 'SUSPENDED';

    /** Restoran to'liq ishlay oladimi (docs/06 §4). */
    public function isOperational(): bool
    {
        return in_array($this, [self::TRIAL, self::ACTIVE, self::EXPIRING], true);
    }

    /** Bloklangan holatlar — grace period alohida tekshiriladi. */
    public function isBlocked(): bool
    {
        return ! $this->isOperational();
    }
}
