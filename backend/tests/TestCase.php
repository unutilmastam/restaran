<?php

declare(strict_types=1);

namespace Tests;

use App\Support\RestaurantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RestaurantContext static holat saqlaydi — testlar orasida
        // oqib ketmasligi uchun har safar tozalanadi (docs/07 §2).
        RestaurantContext::reset();
    }

    protected function tearDown(): void
    {
        RestaurantContext::reset();

        parent::tearDown();
    }
}
