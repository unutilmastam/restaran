<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Joriy so'rov qaysi restoran nomidan ishlayotganini saqlaydi —
 * docs/07-DB-DECISIONS.md §2.
 *
 * ⚠️ ENG MUHIM QOIDA: global scope ROLGA qarab chetlab o'tilmaydi.
 * Agar `role === SUPER_ADMIN` tekshirilsa, SUPER_ADMIN `/admin/*` ga
 * kirganda ham barcha restoranlarni ko'rardi va multi-tenant izolyatsiya
 * buzilardi. Chetlab o'tish faqat `AllowCrossRestaurant` middleware
 * orqali, u esa FAQAT `/api/v1/super/*` guruhiga ulanadi.
 */
final class RestaurantContext
{
    private static bool $unscoped = false;

    private static ?int $restaurantId = null;

    /** Faqat AllowCrossRestaurant middleware chaqiradi. */
    public static function allowCrossRestaurant(): void
    {
        self::$unscoped = true;
    }

    public static function isUnscoped(): bool
    {
        return self::$unscoped;
    }

    /**
     * Restoranni aniq belgilash — customer oqimida `nfc_token` yoki
     * `customer_token` orqali (u yerda `auth()->user()` yo'q).
     */
    public static function set(?int $restaurantId): void
    {
        self::$restaurantId = $restaurantId;
    }

    public static function get(): ?int
    {
        return self::$restaurantId ?? auth()->user()?->restaurant_id;
    }

    /** Testlar orasida holat oqib ketmasligi uchun. */
    public static function reset(): void
    {
        self::$unscoped = false;
        self::$restaurantId = null;
    }
}
