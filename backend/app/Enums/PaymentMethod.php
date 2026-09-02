<?php

declare(strict_types=1);

namespace App\Enums;

/** docs/01-ARCHITECTURE.md §3. Kelajakda PAYME / CLICK / UZUM qo'shiladi. */
enum PaymentMethod: string
{
    case CASH = 'CASH';
    case CARD = 'CARD';
    case OTHER = 'OTHER';
}
