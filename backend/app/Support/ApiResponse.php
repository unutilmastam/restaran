<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Barcha API javoblarining yagona konverti (docs/01-ARCHITECTURE.md §9):
 *
 *   { success, data, message_ru, message_uz, error_code }
 *
 * Ikkala til HAM qaytadi (docs/02-I18N-RU-UZ.md §4) — frontend qaysi biri
 * kerakligini o'zi tanlaydi, shuning uchun til almashtirilganda sahifani
 * qayta yuklash shart emas.
 */
final class ApiResponse
{
    public static function success(mixed $data = null, ?string $messageKey = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message_ru' => $messageKey ? self::translate($messageKey, 'ru') : null,
            'message_uz' => $messageKey ? self::translate($messageKey, 'uz') : null,
            'error_code' => null,
        ], $status);
    }

    /**
     * @param  string  $errorCode  docs/02-I18N-RU-UZ.md §6 lug'atidagi kalit
     */
    public static function error(string $errorCode, int $status = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => $data,
            'message_ru' => self::translate("errors.{$errorCode}", 'ru'),
            'message_uz' => self::translate("errors.{$errorCode}", 'uz'),
            'error_code' => $errorCode,
        ], $status);
    }

    private static function translate(string $key, string $locale): string
    {
        $line = trans($key, [], $locale);

        // Kalit topilmasa trans() kalitning o'zini qaytaradi — bu i18n
        // bo'shlig'ini yashirmasdan ko'rsatadi (docs/02 §1).
        return is_string($line) ? $line : $key;
    }
}
