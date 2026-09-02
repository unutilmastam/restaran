<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §3. BUSY ni waiter o'zi qo'ya olmaydi — tizim qo'yadi. */
enum WaiterStatus: string
{
    case FREE = 'FREE';
    case BUSY = 'BUSY';
    case OFFLINE = 'OFFLINE';
}
