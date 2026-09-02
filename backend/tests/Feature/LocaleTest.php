<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/02-I18N-RU-UZ.md §2 va §10 — til aniqlash zanjiri va
 * `Accept-Language` header'iga to'g'ri javob berish.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_language_header_sets_the_application_locale(): void
    {
        $this->withHeader('Accept-Language', 'ru')->getJson('/api/v1/health');
        $this->assertSame('ru', app()->getLocale());

        $this->withHeader('Accept-Language', 'uz')->getJson('/api/v1/health');
        $this->assertSame('uz', app()->getLocale());
    }

    public function test_query_parameter_wins_over_the_header(): void
    {
        $this->withHeader('Accept-Language', 'ru')->getJson('/api/v1/health?lang=uz');

        $this->assertSame('uz', app()->getLocale());
    }

    public function test_unsupported_language_falls_back_to_the_default(): void
    {
        $this->withHeader('Accept-Language', 'de')->getJson('/api/v1/health');

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_error_responses_always_carry_both_languages(): void
    {
        // docs/02 §4 — javobda ikkala til ham qaytadi, frontend tanlaydi
        $this->getJson('/api/v1/mavjud-emas')
            ->assertJson(fn ($json) => $json
                ->where('message_uz', 'Topilmadi')
                ->where('message_ru', 'Не найдено')
                ->etc());
    }
}
