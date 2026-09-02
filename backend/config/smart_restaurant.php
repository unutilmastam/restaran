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
    'draft_ttl_minutes' => (int) env('SR_DRAFT_TTL_MINUTES', 30),

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

    /*
    |----------------------------------------------------------------------
    | SaaS qatlami — docs/06-SAAS.md
    |----------------------------------------------------------------------
    */
    'saas' => [

        // §3 — yangi restoran TRIAL bilan boshlanadi, tugagach oddiy
        // EXPIRED oqimiga o'tadi (alohida holat emas).
        'trial_days' => (int) env('SR_TRIAL_DAYS', 7),

        // §3 — expires_at gacha shu kundan kam qolsa status EXPIRING.
        'expiring_threshold_days' => (int) env('SR_EXPIRING_THRESHOLD_DAYS', 5),

        // §4 — EXPIRED bo'lgach admin panel shu muddat davomida read-only
        // ishlaydi (hisobotni yuklab olish uchun), keyin to'liq bloklanadi.
        'grace_period_days' => (int) env('SR_GRACE_PERIOD_DAYS', 3),

        // §8 — limit TARIFGA emas, RESTORANGA biriktiriladi. Bular faqat
        // yangi restoran yaratilgandagi boshlang'ich qiymat; keyin
        // SUPER_ADMIN har restoran uchun alohida o'zgartiradi.
        'default_limits' => [
            'max_tables' => (int) env('SR_MAX_TABLES', 30),
            'max_products' => (int) env('SR_MAX_PRODUCTS', 100),
            'max_waiters' => (int) env('SR_MAX_WAITERS', 10),
        ],

        // §6 — yangi bot. Bo'sh bo'lsa xabarnoma jim o'chadi.
        'telegram' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'username' => env('TELEGRAM_BOT_USERNAME'),
        ],
    ],
];
