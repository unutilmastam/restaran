<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;

/**
 * docs/06-SAAS.md §8 (javob 7) — limitlar TARIFGA emas, RESTORANGA
 * biriktirilgan. SUPER_ADMIN har restoran uchun alohida o'zgartiradi.
 */
class LimitService
{
    public function assertCanAddTable(Restaurant $restaurant): void
    {
        $this->assert(Table::query()->count(), $restaurant->max_tables);
    }

    public function assertCanAddProduct(Restaurant $restaurant): void
    {
        $this->assert(Product::query()->count(), $restaurant->max_products);
    }

    public function assertCanAddWaiter(Restaurant $restaurant): void
    {
        $this->assert(
            User::query()->where('role', UserRole::WAITER)->count(),
            $restaurant->max_waiters,
        );
    }

    /** @return array{tables: array{used: int, max: int}, products: array{used: int, max: int}, waiters: array{used: int, max: int}} */
    public function usage(Restaurant $restaurant): array
    {
        return [
            'tables' => [
                'used' => Table::query()->count(),
                'max' => $restaurant->max_tables,
            ],
            'products' => [
                'used' => Product::query()->count(),
                'max' => $restaurant->max_products,
            ],
            'waiters' => [
                'used' => User::query()->where('role', UserRole::WAITER)->count(),
                'max' => $restaurant->max_waiters,
            ],
        ];
    }

    private function assert(int $used, int $max): void
    {
        if ($used >= $max) {
            throw new BusinessException('LIMIT_EXCEEDED', 422, ['used' => $used, 'max' => $max]);
        }
    }
}
