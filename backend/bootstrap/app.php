<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Til aniqlash har bir API so'rovida ishlaydi (docs/02 §2).
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API javoblari HAR DOIM bitta konvertda qaytadi (docs/01 §9):
        // { success, data, message_ru, message_uz, error_code }
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof BusinessException => null, // o'zining render() metodi bor
                $e instanceof ValidationException => ApiResponse::error(
                    'VALIDATION_FAILED', 422, ['fields' => $e->errors()]
                ),
                $e instanceof AuthenticationException => ApiResponse::error('UNAUTHENTICATED', 401),
                $e instanceof AccessDeniedHttpException,
                $e instanceof AuthorizationException => ApiResponse::error('FORBIDDEN', 403),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error('NOT_FOUND', 404),
                $e instanceof ThrottleRequestsException => ApiResponse::error('TOO_MANY_REQUESTS', 429),
                default => null,
            };
        });
    })->create();
