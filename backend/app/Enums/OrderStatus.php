<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * docs/01-ARCHITECTURE.md §3 + docs/05-PHASE0-PLAN.md §2.4 (DRAFT).
 *
 * Transition matritsasi shu yerda — Service'lar `canTransitionTo()` ni
 * chaqiradi, o'z shartlarini yozmaydi.
 */
enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case WAITING_FOR_WAITER = 'WAITING_FOR_WAITER';
    case ASSIGNED = 'ASSIGNED';
    case WAITER_ACCEPTED = 'WAITER_ACCEPTED';
    case DELIVERING = 'DELIVERING';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING, self::CANCELLED, self::EXPIRED],
            self::PENDING => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::ASSIGNED, self::WAITING_FOR_WAITER, self::CANCELLED],
            self::WAITING_FOR_WAITER => [self::ASSIGNED, self::CANCELLED],
            self::ASSIGNED => [self::WAITER_ACCEPTED],
            self::WAITER_ACCEPTED => [self::DELIVERING],
            self::DELIVERING => [self::DELIVERED],
            self::DELIVERED, self::CANCELLED, self::EXPIRED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** DRAFT hech qanday ro'yxatda, broadcastda va assignmentda qatnashmaydi. */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    /** "Yetkazilmagan order bor" tekshiruvi uchun (CLAUDE.md §2.4). */
    public function isOpen(): bool
    {
        return ! $this->isFinal() && ! $this->isDraft();
    }
}
