<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
| 3 ta frontend alohida origin'da turadi (docs/05-PHASE0-PLAN.md §1):
|   domain.uz · admin.domain.uz · waiter.domain.uz → api.domain.uz
|
| Auth Sanctum TOKEN orqali (cookie emas), shuning uchun
| `supports_credentials` false — bu CSRF yuzasini kichraytiradi.
*/

return [

    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Customer-Token',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];
