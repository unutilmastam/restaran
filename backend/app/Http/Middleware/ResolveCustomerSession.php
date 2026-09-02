<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use App\Services\SessionService;
use App\Support\RestaurantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `X-Customer-Token` header'i bo'yicha sessionni topadi.
 *
 * Token DB'da hash sifatida saqlanadi, shuning uchun qidiruv
 * `SessionService::findByCustomerToken()` orqali (docs/05 §2.3).
 */
class ResolveCustomerSession
{
    public function __construct(private readonly SessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Customer-Token');

        if (! is_string($token) || $token === '') {
            throw new BusinessException('SESSION_NOT_FOUND', 401);
        }

        // Kontekst hali aniqlanmagan bo'lishi mumkin (bu route'da
        // nfc_token yo'q), shuning uchun scope vaqtincha ochiladi va
        // session topilgach DARHOL o'z restoraniga qulflanadi.
        RestaurantContext::allowCrossRestaurant();
        $session = $this->sessions->findByCustomerToken($token);
        RestaurantContext::reset();

        if ($session === null) {
            throw new BusinessException('SESSION_NOT_FOUND', 401);
        }

        RestaurantContext::set($session->restaurant_id);
        $request->attributes->set('session', $session);

        return $next($request);
    }
}
