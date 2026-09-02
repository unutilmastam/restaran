<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Smart Restaurant — biznes sozlamalari
|--------------------------------------------------------------------------
| Bu yerdagi qiymatlar docs/05-PHASE0-PLAN.md §5 dagi tasdiqlangan
| javoblardan kelib chiqadi. Restoran darajasidagi override `settings`
| jadvalida bo'ladi (PHASE 2).
*/

return [

    // Draft order (docs/01 §12) yashash muddati. Muddati o'tgani EXPIRED bo'ladi,
    // shunda uzoq turgan cart to'lovdan keyin "tirilib" ketmaydi.
    'draft_ttl_minutes' => (int) env('SR_DRAFT_TTL_MINUTES', 120),

    // Customer real-time: WebSocket EMAS, polling.
    // Sabab: Pusher free plan = 100 concurrent connection.
    'customer_poll_seconds' => (int) env('SR_CUSTOMER_POLL_SECONDS', 4),

    // Afitsant chaqiruvi spam himoyasi (docs/03 PHASE 11).
    'waiter_call_cooldown_minutes' => (int) env('SR_WAITER_CALL_COOLDOWN_MINUTES', 2),

    // Rasm yuklash limitlari — 1 GB disk byudjeti (docs/05 §0).
    'image' => [
        'max_width' => (int) env('SR_IMAGE_MAX_WIDTH', 1200),
        'max_kb' => (int) env('SR_IMAGE_MAX_KB', 300),
        'format' => env('SR_IMAGE_FORMAT', 'webp'),
        'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    // Retention — disk byudjeti (docs/05 §0).
    'retention' => [
        'activity_logs_days' => 90,
        'notifications_days' => 30,
    ],

    'locales' => ['uz', 'ru'],
];
