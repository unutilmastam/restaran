<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Til aniqlash tartibi (docs/02-I18N-RU-UZ.md §2):
 *
 *   URL param (?lang=) → Accept-Language header → user.locale
 *   → restaurant.default_locale → 'uz'
 *
 * restaurant.default_locale PHASE 2 da (Restaurant modeli paydo bo'lgach)
 * shu zanjirga qo'shiladi — hozircha config fallback ishlaydi.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('smart_restaurant.locales');

        $locale = $this->fromQuery($request, $supported)
            ?? $this->fromHeader($request, $supported)
            ?? $this->fromUser($request, $supported)
            ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }

    /** @param  list<string>  $supported */
    private function fromQuery(Request $request, array $supported): ?string
    {
        $lang = $request->query('lang');

        return is_string($lang) && in_array($lang, $supported, true) ? $lang : null;
    }

    /** @param  list<string>  $supported */
    private function fromHeader(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $short = substr($language, 0, 2);

            if (in_array($short, $supported, true)) {
                return $short;
            }
        }

        return null;
    }

    /** @param  list<string>  $supported */
    private function fromUser(Request $request, array $supported): ?string
    {
        $locale = $request->user()?->locale;

        return is_string($locale) && in_array($locale, $supported, true) ? $locale : null;
    }
}
