<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Pul ustunlari uchun cast — docs/07-DB-DECISIONS.md §5.
 *
 * Muammo: Laravel'ning `decimal:2` cast'i STRING qaytaradi, shuning uchun
 * API javobida `"310000.00"` ko'rinadi. Frontend har safar `Number()`
 * qilishga majbur bo'ladi va `formatMoney` xato ishlaydi.
 *
 * Yechim:
 *   get → float  (JSON'da 310000 — son)
 *   set → '310000.00' (DECIMAL ustunga aniq matn; float to'g'ridan-to'g'ri
 *         yozilsa yaxlitlash chetlanishi bo'lishi mumkin)
 *
 * ⚠️ QAT'IY QOIDA: pul PHP'da YIG'ILMAYDI. Barcha SUM/total/subtotal
 * hisoblari SQL'da (DECIMAL arifmetikasi) bajariladi. Bu cast faqat
 * chegara — DB bilan JSON o'rtasida.
 *
 * DECIMAL(12,2) → max 9 999 999 999.99. Double 15-16 ta muhim raqamni
 * aniq saqlaydi, shuning uchun bu diapazonda chiqarishda yo'qotish yo'q.
 *
 * @implements CastsAttributes<float|null, float|int|string|null>
 */
final class Money implements CastsAttributes
{
    public const PRECISION = 2;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        return [$key => number_format((float) $value, self::PRECISION, '.', '')];
    }
}
