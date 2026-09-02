<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Restaurant> */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'phone' => fake()->numerify('+9989########'),
            'currency' => 'UZS',
            'default_locale' => 'uz',
            'timezone' => 'Asia/Tashkent',
            'is_active' => true,
            'subscription_status' => SubscriptionStatus::ACTIVE,
            'expires_at' => now()->addYear(),
            'max_tables' => 30,
            'max_products' => 100,
            'max_waiters' => 10,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (): array => [
            'subscription_status' => SubscriptionStatus::TRIAL,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'subscription_status' => SubscriptionStatus::EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}
