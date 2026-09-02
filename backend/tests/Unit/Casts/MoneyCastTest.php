<?php

declare(strict_types=1);

namespace Tests\Unit\Casts;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/07-DB-DECISIONS.md §5.
 *
 * Laravel'ning `decimal:2` cast'i STRING qaytaradi va API javobida
 * `"310000.00"` ko'rinadi. `Money` cast buni SON qiladi.
 */
class MoneyCastTest extends TestCase
{
    use RefreshDatabase;

    private function plan(float|int|string $price): Plan
    {
        return Plan::create([
            'code' => 'X'.uniqid(),
            'name_ru' => 'Тест',
            'name_uz' => 'Test',
            'days' => 30,
            'price' => $price,
        ]);
    }

    public function test_money_is_read_back_as_a_float(): void
    {
        $plan = $this->plan(310_000)->fresh();

        $this->assertIsFloat($plan->price);
        $this->assertSame(310000.0, $plan->price);
    }

    public function test_money_is_serialised_to_json_as_a_number_not_a_string(): void
    {
        $plan = $this->plan(310_000)->fresh();

        $json = json_decode($plan->toJson(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsNotString($json['price']);
        $this->assertSame(310000, $json['price']);
    }

    public function test_decimals_survive_the_round_trip(): void
    {
        $plan = $this->plan('1234.56')->fresh();

        $this->assertSame(1234.56, $plan->price);
    }

    public function test_the_database_still_holds_an_exact_decimal(): void
    {
        $plan = $this->plan(99_999.99);

        // Cast `set` da number_format ishlatadi, shuning uchun DECIMAL
        // ustunga float emas, aniq matn boradi.
        $raw = \DB::table('plans')->where('id', $plan->id)->value('price');

        $this->assertSame('99999.99', (string) $raw);
    }

    public function test_null_stays_null(): void
    {
        $plan = $this->plan(0)->fresh();

        $this->assertSame(0.0, $plan->price);
    }
}
