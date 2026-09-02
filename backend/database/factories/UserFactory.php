<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\WaiterStatus;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->name(),
            'username' => Str::lower(Str::random(10)),
            'phone' => fake()->numerify('+9989########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::WAITER,
            'status' => WaiterStatus::FREE,
            'last_free_at' => now(),
            'locale' => 'uz',
            'is_active' => true,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'restaurant_id' => null,
            'role' => UserRole::SUPER_ADMIN,
            'status' => null,
            'last_free_at' => null,
        ]);
    }

    public function ownerAdmin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::OWNER_ADMIN,
            'status' => null,
            'last_free_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::ADMIN,
            'status' => null,
            'last_free_at' => null,
        ]);
    }
}
