<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `ResolveCustomer` dan keyin: HAQIQIY session talab qiladigan
 * endpointlar uchun (`/sessions/me`).
 *
 * Draft tokeni bu yerdan o'ta olmaydi — draft egasi hali hech qanday
 * sessionga a'zo emas.
 */
class RequireCustomerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('session') === null) {
            throw new BusinessException('SESSION_NOT_FOUND', 401);
        }

        return $next($request);
    }
}
