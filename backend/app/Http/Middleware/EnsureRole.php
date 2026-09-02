<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Support\RestaurantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rol tekshiruvi + restoran kontekstini o'rnatish.
 *
 * ⚠️ SUPER_ADMIN `/admin/*` da IMTIYOZ OLMAYDI: uning `restaurant_id`
 * `null`, shuning uchun global scope hech nima qaytarmaydi va u bu
 * yerda ishlay olmaydi. Platforma boshqaruvi `/super/*` da
 * (docs/07-DB-DECISIONS.md §2).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new BusinessException('UNAUTHENTICATED', 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            throw new BusinessException('FORBIDDEN', 403);
        }

        if (! $user->is_active) {
            throw new BusinessException('FORBIDDEN', 403);
        }

        // Kontekst FAQAT foydalanuvchining o'z restoranidan olinadi —
        // hech qachon request'dan (docs/06-SAAS.md §10.2).
        if ($user->role !== UserRole::SUPER_ADMIN) {
            RestaurantContext::set($user->restaurant_id);
        }

        return $next($request);
    }
}
