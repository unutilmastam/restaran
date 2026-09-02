<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * docs/06-SAAS.md §2 — obuna to'lovi. Bu `PaymentMethod` dan ALOHIDA:
 * u restoran mijozining hisobi uchun, bu esa restoranning platformaga
 * to'lovi uchun. Ikkalasi hech qachon aralashtirilmaydi.
 */
enum SubscriptionPaymentMethod: string
{
    case CLICK = 'CLICK';
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';
    case OTHER = 'OTHER';
}
