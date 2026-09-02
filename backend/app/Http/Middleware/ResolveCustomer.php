<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use App\Models\SessionDevice;
use App\Services\SessionService;
use App\Support\RestaurantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `X-Customer-Token` ni yechadi. IKKI xil token bo'lishi mumkin:
 *
 *   1. SESSION tokeni — `session_devices` da yozuvi bor
 *   2. DRAFT tokeni   — hali sessionga bog'lanmagan, faqat
 *                       `orders.created_by_token_hash` da yashaydi
 *
 * Ikkinchisi WAITING_PAYMENT stolda buyurtma bergan mijozga beriladi:
 * u ketayotgan mijozning sessioniga ULANMAYDI (docs/01 §12), lekin o'z
 * draftini kuzata olishi kerak.
 *
 * Request atributlari: `session` (nullable) va `customer_token_hash`.
 */
class ResolveCustomer
{
    public function __construct(private readonly SessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Customer-Token');

        if (! is_string($token) || $token === '') {
            throw new BusinessException('SESSION_NOT_FOUND', 401);
        }

        // Kontekst hali aniqlanmagan — scope vaqtincha ochiladi va
        // session topilgach DARHOL o'z restoraniga qulflanadi.
        RestaurantContext::allowCrossRestaurant();
        $session = $this->sessions->findByCustomerToken($token);
        RestaurantContext::reset();

        $request->attributes->set('session', $session);
        $request->attributes->set('customer_token_hash', SessionDevice::hashToken($token));

        if ($session !== null) {
            RestaurantContext::set($session->restaurant_id);
        }

        return $next($request);
    }
}
