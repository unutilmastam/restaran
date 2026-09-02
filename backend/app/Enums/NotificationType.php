<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §5 (notifications.type). */
enum NotificationType: string
{
    case NEW_ORDER = 'NEW_ORDER';
    case WAITER_CALL = 'WAITER_CALL';
    case ORDER_ACCEPTED = 'ORDER_ACCEPTED';
    case ORDER_ASSIGNED = 'ORDER_ASSIGNED';
    case ORDER_DELIVERED = 'ORDER_DELIVERED';
    case PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';

    /** docs/01 §11 — bu turlar admin panelida ovoz bilan o'qiladi. */
    public function isSpoken(): bool
    {
        return in_array($this, [self::NEW_ORDER, self::WAITER_CALL], true);
    }
}
