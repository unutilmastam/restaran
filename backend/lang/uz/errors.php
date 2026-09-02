<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| XATO KODLARI — UZ
|--------------------------------------------------------------------------
| docs/02-I18N-RU-UZ.md §6 lug'ati. Kalitlar `lang/ru/errors.php` bilan
| 1:1 mos bo'lishi SHART — LocaleParityTest buni tekshiradi.
*/

return [

    // docs/02 §6 — majburiy lug'at
    'PRODUCT_UNAVAILABLE' => 'Mahsulot hozir mavjud emas',
    'SESSION_NOT_FOUND' => 'Bu stol uchun faol sessiya topilmadi',
    'ORDER_NOT_DELIVERED' => 'Avvalgi buyurtmangiz hali yetkazilmagan',
    'SESSION_WAITING_PAYMENT' => 'Turib ketgan mijoz to\'lov qilyapti. Iltimos, bir oz kuting.',
    'NO_FREE_WAITER' => 'Hozircha bo\'sh afitsant mavjud emas',
    'NETWORK_ERROR' => 'Internet bilan aloqa uzildi',
    'ORDER_DUPLICATE' => 'Buyurtma allaqachon yuborilgan',
    'INVALID_TABLE' => 'Stol topilmadi',
    'INVALID_STATUS_TRANSITION' => 'Bu amalni bajarib bo\'lmaydi',
    'PRICE_CHANGED' => 'Narxlar yangilandi, savatni tekshiring',

    // Umumiy HTTP holatlari
    'VALIDATION_FAILED' => 'Yuborilgan ma\'lumotlar noto\'g\'ri',
    'UNAUTHENTICATED' => 'Tizimga kiring',
    'FORBIDDEN' => 'Bu amal uchun ruxsatingiz yo\'q',
    'NOT_FOUND' => 'Topilmadi',
    'TOO_MANY_REQUESTS' => 'Juda ko\'p so\'rov yuborildi. Biroz kuting.',
    'SERVER_ERROR' => 'Serverda xatolik yuz berdi',

    // SaaS — docs/06-SAAS.md
    'LIMIT_EXCEEDED' => 'Limitga yetdingiz. Kengaytirish uchun bog\'laning.',
    'SUBSCRIPTION_EXPIRED' => 'Restoran obunasi tugagan',
    'SUBSCRIPTION_SUSPENDED' => 'Restoran vaqtincha to\'xtatilgan',
    'RESTAURANT_UNAVAILABLE' => 'Restoran vaqtincha ishlamayapti',
    'RESTAURANT_ARCHIVED' => 'Restoran arxivlangan',
    'OWNER_ADMIN_PROTECTED' => 'Restoran egasining hisobini o\'chirib bo\'lmaydi',
    'CONFIRMATION_MISMATCH' => 'Tasdiqlash matni mos kelmadi',
];
