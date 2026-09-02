<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use App\Models\Table;
use App\Support\RestaurantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/01-ARCHITECTURE.md §4 — NFC kirish nuqtasi.
 *
 * Customer oqimida `auth()` YO'Q, shuning uchun restoranni aynan shu
 * middleware aniqlaydi va `RestaurantContext` ga yozadi. Shundan keyingina
 * global scope ishlay boshlaydi (docs/07-DB-DECISIONS.md §2).
 *
 * ⚠️ Qidiruv `withoutGlobalScopes()` bilan: shu paytda kontekst hali yo'q,
 * scope esa `whereRaw('1 = 0')` qaytarardi. Bu YAGONA joy — boshqa hech
 * qayerda scope bunday chetlab o'tilmaydi.
 */
class ResolveTableByNfcToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $table = Table::withoutGlobalScopes()
            ->with('restaurant')
            ->where('nfc_token', (string) $request->route('nfc_token'))
            ->first();

        // Stol yo'q · o'chirilgan · restoran arxivlangan yoki o'chirilgan —
        // hammasi BIR XIL javob beradi, shunda tashqaridan token mavjudmi
        // yoki yo'qmi aniqlab bo'lmaydi.
        if ($table === null
            || ! $table->is_active
            || $table->restaurant === null
            || ! $table->restaurant->is_active) {
            throw new BusinessException('INVALID_TABLE', 404);
        }

        // docs/06-SAAS.md §7 — /r/{slug}/t/{nfc_token}. Slug faqat
        // ko'rinish uchun, lekin noto'g'ri bo'lsa xato beramiz.
        $slug = $request->route('slug');

        if ($slug !== null && $slug !== $table->restaurant->slug) {
            throw new BusinessException('INVALID_TABLE', 404);
        }

        RestaurantContext::set($table->restaurant_id);
        $request->attributes->set('table', $table);

        return $next($request);
    }
}
