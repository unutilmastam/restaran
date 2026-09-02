<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\SubscriptionPaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\SubscriptionPayment;
use App\Support\RestaurantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/07-DB-DECISIONS.md §3 — 4 qatlamli snapshot kafolati.
 *
 * Super admin tarif narxini o'zgartirsa, o'tgan to'lovlar va moliyaviy
 * hisobot BUZILMASLIGI kerak.
 */
class PlanPriceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantContext::allowCrossRestaurant();

        $this->restaurant = Restaurant::factory()->create();
        $this->plan = Plan::create([
            'code' => 'YEARLY',
            'name_ru' => '1 год',
            'name_uz' => '1 yillik',
            'days' => 365,
            'price' => 1_200_000,
        ]);
    }

    private function payment(): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'plan_id' => $this->plan->id,
            'amount' => $this->plan->price,
            'plan_code_snapshot' => $this->plan->code,
            'plan_name_ru_snapshot' => $this->plan->name_ru,
            'plan_name_uz_snapshot' => $this->plan->name_uz,
            'plan_days_snapshot' => $this->plan->days,
            'method' => SubscriptionPaymentMethod::CLICK,
            'paid_at' => now(),
        ]);
    }

    public function test_changing_the_plan_price_leaves_past_payments_untouched(): void
    {
        $payment = $this->payment();

        $this->plan->update(['price' => 2_000_000, 'name_uz' => '1 yillik (yangi)']);

        $payment->refresh();

        $this->assertSame(1_200_000.0, $payment->amount);
        $this->assertSame('1 yillik', $payment->plan_name_uz_snapshot);
        $this->assertSame(365, $payment->plan_days_snapshot);
    }

    public function test_reports_read_the_snapshot_not_the_current_plan(): void
    {
        $this->payment();
        $this->plan->update(['price' => 2_000_000]);

        $total = SubscriptionPayment::withoutGlobalScopes()->sum('amount');

        // Hisobot `plans` ga JOIN qilmaydi — aks holda bu 2 000 000 bo'lardi.
        $this->assertSame('1200000.00', (string) $total);
    }

    public function test_a_payment_record_can_never_be_updated(): void
    {
        $payment = $this->payment();

        $this->expectException(RuntimeException::class);
        $payment->update(['amount' => 1]);
    }

    public function test_a_payment_record_can_never_be_deleted(): void
    {
        $payment = $this->payment();

        $this->expectException(RuntimeException::class);
        $payment->delete();
    }

    public function test_a_plan_with_payments_cannot_be_deleted(): void
    {
        $this->payment();

        // FK RESTRICT — o'rniga is_active = false qilinadi.
        $this->expectException(QueryException::class);
        $this->plan->delete();
    }

    public function test_the_localised_plan_name_comes_from_the_snapshot(): void
    {
        $payment = $this->payment();
        $this->plan->update(['name_ru' => 'ИЗМЕНЕНО', 'name_uz' => 'OZGARDI']);

        $payment->refresh();

        $this->assertSame('1 yillik', $payment->planName('uz'));
        $this->assertSame('1 год', $payment->planName('ru'));
    }
}
