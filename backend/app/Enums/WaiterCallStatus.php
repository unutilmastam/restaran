<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §3. */
enum WaiterCallStatus: string
{
    case PENDING = 'PENDING';
    case ASSIGNED = 'ASSIGNED';
    case ACCEPTED = 'ACCEPTED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
