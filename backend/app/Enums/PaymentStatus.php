<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §3 — stol hisobi to'lovi. */
enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case REFUNDED = 'REFUNDED';
}
