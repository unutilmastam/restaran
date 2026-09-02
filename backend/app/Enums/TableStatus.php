<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * docs/01-ARCHITECTURE.md §3.
 *
 * ⚠️ Bu DENORMALIZATSIYA — session va order holatidan kelib chiqadi.
 * Yagona yozuvchi `TableStatusService::recalculate()`. Boshqa hech qayerda
 * `$table->status = ...` yozilmaydi (docs/05-PHASE0-PLAN.md §2.6).
 */
enum TableStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case ACTIVE = 'ACTIVE';
    case ORDER_PENDING = 'ORDER_PENDING';
    case WAITER_ASSIGNED = 'WAITER_ASSIGNED';
    case DELIVERED = 'DELIVERED';
    case WAITING_PAYMENT = 'WAITING_PAYMENT';
}
