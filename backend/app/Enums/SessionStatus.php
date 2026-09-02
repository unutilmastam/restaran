<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §3: ACTIVE → WAITING_PAYMENT → PAID → CLOSED */
enum SessionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case WAITING_PAYMENT = 'WAITING_PAYMENT';
    case PAID = 'PAID';
    case CLOSED = 'CLOSED';

    /**
     * Stol band hisoblanadigan holatlar. `table_sessions.active_key`
     * generated column AYNAN shu ro'yxatga tayanadi —
     * o'zgartirilsa migration ham o'zgarishi kerak (docs/07 §6).
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::ACTIVE, self::WAITING_PAYMENT];
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ACTIVE => [self::WAITING_PAYMENT, self::PAID, self::CLOSED],
            self::WAITING_PAYMENT => [self::PAID, self::CLOSED],
            self::PAID => [self::CLOSED],
            self::CLOSED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
