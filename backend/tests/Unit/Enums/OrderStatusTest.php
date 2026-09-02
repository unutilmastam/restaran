<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * docs/01-ARCHITECTURE.md §3 + docs/05-PHASE0-PLAN.md §2.4.
 *
 * Transition matritsasining yagona manbai — enum. Service'lar o'z
 * shartlarini yozmaydi, `canTransitionTo()` ni chaqiradi.
 */
class OrderStatusTest extends TestCase
{
    /** @return list<array{OrderStatus, OrderStatus}> */
    public static function allowedTransitions(): array
    {
        return [
            [OrderStatus::DRAFT, OrderStatus::PENDING],
            [OrderStatus::DRAFT, OrderStatus::EXPIRED],
            [OrderStatus::PENDING, OrderStatus::ACCEPTED],
            [OrderStatus::PENDING, OrderStatus::CANCELLED],
            [OrderStatus::ACCEPTED, OrderStatus::ASSIGNED],
            [OrderStatus::ACCEPTED, OrderStatus::WAITING_FOR_WAITER],
            [OrderStatus::WAITING_FOR_WAITER, OrderStatus::ASSIGNED],
            [OrderStatus::ASSIGNED, OrderStatus::WAITER_ACCEPTED],
            [OrderStatus::WAITER_ACCEPTED, OrderStatus::DELIVERING],
            [OrderStatus::DELIVERING, OrderStatus::DELIVERED],
        ];
    }

    /** @return list<array{OrderStatus, OrderStatus}> */
    public static function forbiddenTransitions(): array
    {
        return [
            // docs/04-TEST-SCENARIO.md — majburiy test
            [OrderStatus::PENDING, OrderStatus::DELIVERED],
            [OrderStatus::PENDING, OrderStatus::ASSIGNED],
            [OrderStatus::DELIVERED, OrderStatus::PENDING],
            [OrderStatus::CANCELLED, OrderStatus::ACCEPTED],
            [OrderStatus::ASSIGNED, OrderStatus::DELIVERED],
            [OrderStatus::DRAFT, OrderStatus::ACCEPTED],
            [OrderStatus::ACCEPTED, OrderStatus::WAITER_ACCEPTED],
            [OrderStatus::EXPIRED, OrderStatus::PENDING],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_are_accepted(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to), "{$from->value} → {$to->value} ruxsat etilishi kerak");
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_forbidden_transitions_are_rejected(OrderStatus $from, OrderStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to), "{$from->value} → {$to->value} TAQIQLANGAN");
    }

    public function test_final_statuses_have_no_way_out(): void
    {
        foreach ([OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::EXPIRED] as $status) {
            $this->assertTrue($status->isFinal());
            $this->assertSame([], $status->allowedTransitions());
        }
    }

    public function test_draft_is_never_treated_as_an_open_order(): void
    {
        // DRAFT "yetkazilmagan order bor" tekshiruvига tushmasligi kerak,
        // aks holda mijoz ikkinchi buyurtma bera olmay qolardi.
        $this->assertTrue(OrderStatus::DRAFT->isDraft());
        $this->assertFalse(OrderStatus::DRAFT->isOpen());
    }

    public function test_open_statuses_block_a_new_order(): void
    {
        foreach ([
            OrderStatus::PENDING,
            OrderStatus::ACCEPTED,
            OrderStatus::WAITING_FOR_WAITER,
            OrderStatus::ASSIGNED,
            OrderStatus::WAITER_ACCEPTED,
            OrderStatus::DELIVERING,
        ] as $status) {
            $this->assertTrue($status->isOpen(), "{$status->value} ochiq hisoblanishi kerak");
        }

        foreach ([OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::EXPIRED] as $status) {
            $this->assertFalse($status->isOpen());
        }
    }

    public function test_there_is_no_kitchen_status(): void
    {
        // CLAUDE.md §2.1 — oshpaz tizimdan foydalanmaydi.
        foreach (OrderStatus::cases() as $status) {
            $this->assertStringNotContainsStringIgnoringCase('kitchen', $status->value);
            $this->assertStringNotContainsStringIgnoringCase('cook', $status->value);
        }
    }
}
