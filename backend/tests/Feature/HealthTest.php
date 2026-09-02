<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok');
    }

    public function test_every_response_uses_the_shared_envelope(): void
    {
        // docs/01-ARCHITECTURE.md §9 — barcha javob bitta shaklda
        $this->getJson('/api/v1/health')
            ->assertJsonStructure(['success', 'data', 'message_ru', 'message_uz', 'error_code']);
    }

    public function test_unknown_api_route_returns_translated_error_envelope(): void
    {
        $this->getJson('/api/v1/mavjud-emas')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'NOT_FOUND')
            ->assertJsonPath('message_uz', 'Topilmadi')
            ->assertJsonPath('message_ru', 'Не найдено');
    }
}
