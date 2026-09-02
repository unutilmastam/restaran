<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/06-SAAS.md §1 — 4 ta rol. */
enum UserRole: string
{
    /** Platforma egasi. `restaurant_id = null`. */
    case SUPER_ADMIN = 'SUPER_ADMIN';

    /** Restoranning birinchi admini — o'chirib bo'lmaydi (docs/06 §1). */
    case OWNER_ADMIN = 'OWNER_ADMIN';

    case ADMIN = 'ADMIN';
    case WAITER = 'WAITER';

    public function isPlatformLevel(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /** Admin panelga kira oladimi. */
    public function isRestaurantAdmin(): bool
    {
        return in_array($this, [self::OWNER_ADMIN, self::ADMIN], true);
    }

    /** OWNER_ADMIN ni o'chirib bo'lmaydi, rolini faqat SUPER_ADMIN o'zgartiradi. */
    public function isDeletable(): bool
    {
        return $this !== self::OWNER_ADMIN;
    }
}
