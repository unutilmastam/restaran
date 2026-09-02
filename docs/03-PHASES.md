# 03 — BOSQICHMA-BOSQICH REJA

Har bir phase uchun: **vazifa → natija → qabul mezoni → tayyor prompt**.

Promptni Claude Code'ga to'g'ridan-to'g'ri ko'chirib qo'yasiz. Bir phase tugamaguncha keyingisini boshlamang.

---

## PHASE 0 — TAYYORGARLIK

**Vazifa:** repo yaratish, `CLAUDE.md` va `docs/` fayllarini joylash.

**Prompt:**
```
Loyihani boshlaymiz. Avval CLAUDE.md va docs/ ichidagi 4 ta faylni o'qi.
Keyin menga quyidagilarni ber:
1. Repository strukturasi rejasi
2. Database schema rejasi (ER munosabatlar bilan)
3. API contract ro'yxati
4. Test strategiyasi
Hech qanday kod yozma. Faqat reja. Noaniq joylar bo'lsa savol ber.
```

**Qabul mezoni:** reja tasdiqlangan, noaniqliklar hal qilingan.

---

## PHASE 1 — PROJECT SETUP

**Vazifa:** Laravel 11 + MySQL + Redis + Sanctum + Reverb o'rnatish. 3 ta React (Vite + TS) app skeleton. i18n skeleton (ru/uz).

**Natija:**
- `backend/` ishga tushadi, `/api/v1/health` javob beradi
- `frontend/customer|admin|waiter` build bo'ladi
- `shared/i18n/ru.json`, `uz.json` mavjud
- `.env.example`, README

**Qabul mezoni:** `php artisan serve` + 3 ta `npm run dev` xatosiz ishlaydi, health endpoint 200.

**Prompt:**
```
PHASE 1 ni bajar: project setup.
- backend/ da Laravel 11: Sanctum, Reverb, Redis (cache+queue), MySQL
- frontend/ da 3 ta Vite+React+TS app: customer, admin, waiter
- frontend/shared/ da: i18n (react-i18next, ru.json + uz.json), api client (axios, Accept-Language header), types
- Tailwind CSS barcha 3 appda
- backend/lang/ru va backend/lang/uz papkalari
- GET /api/v1/health endpoint
- .env.example va README.md (o'rnatish qadamlari)
Kod yozgandan keyin har birini ishga tushirib tekshir. Faqat PHASE 1. Keyingi phase'ga o'tma.
```

**SaaS qo'shimchasi (docs/06-SAAS.md §7):** frontend **4 ta** app:
`customer`, `admin`, `waiter`, **`super`** (`super.itcode.uz` — SUPER_ADMIN paneli).
`.env.example` da `TELEGRAM_BOT_TOKEN` va SaaS default'lari
(`SR_TRIAL_DAYS=7`, `SR_MAX_TABLES=30`, `SR_MAX_PRODUCTS=100`, `SR_MAX_WAITERS=10`).

---

## PHASE 2 — DATABASE: MIGRATION + MODEL

**Vazifa:** `docs/01-ARCHITECTURE.md` §5 dagi barcha jadvallar.

**Natija:** 13 ta migration, Eloquent modellar, relationship'lar, enum/cast'lar, seeder (1 restoran, 1 admin, 3 waiter, 20 stol, 5 kategoriya, 25 mahsulot — ru+uz nomlari bilan).

**Qabul mezoni:** `migrate:fresh --seed` xatosiz. Barcha relationship tinker'da tekshirilgan.

**Prompt:**
```
PHASE 2 ni bajar: database.
docs/01-ARCHITECTURE.md §5 dagi schema aynan bajarilsin.
- Barcha migration'lar, foreign key, index, unique constraint bilan
- Eloquent modellar + relationship'lar
- Status'lar uchun PHP Enum klasslar (docs §3)
- restaurant_id barcha kerakli jadvallarda + global scope
- Seeder: 1 restoran, 1 admin, 3 waiter (Hasan, Akmal, Ali), 20 stol (nfc_token random),
  5 kategoriya va 25 mahsulot — name_ru va name_uz to'ldirilgan
migrate:fresh --seed ni ishga tushirib tekshir. Faqat PHASE 2.
```

---

## PHASE 2.5 — SaaS SCHEMA (PHASE 2 ICHIDA)

> ⚠️ **PHASE 2 bilan BIRGA bajariladi.** Keyin qo'shish migration'larni
> chalkashtiradi va `restaurants`/`users` ga `ALTER` talab qiladi.

**Vazifa:** `docs/06-SAAS.md` §2 dagi jadvallar va ustunlar.

**Natija:**
- `plans`, `subscriptions`, `subscription_payments` jadvallari
- `restaurants` ga: `slug`, `subscription_status`, `expires_at`, `owner_phone`,
  `owner_telegram`, `owner_telegram_chat_id`, `logo`, `max_tables`,
  `max_products`, `max_waiters`, `suspended_reason`, `deleted_at`
- `users.role` ga `SUPER_ADMIN` va `OWNER_ADMIN` qo'shiladi
- `settings.restaurant_id` **nullable** (null = platforma darajasi, §12)
- `subscription_payments` da snapshot ustunlari (narx o'zgarsa tarix buzilmaydi)
- Seed: 3 ta plan (narx 0), 1 ta SUPER_ADMIN (`restaurant_id = null`),
  platforma `settings` (`contact_phone`, `contact_telegram`, `contact_note_*`)

**Qabul mezoni:** `migrate:fresh --seed` xatosiz. Har restoranda aynan bitta
`OWNER_ADMIN` bo'lishi DB darajasida ta'minlangan.

**Prompt:**
```
PHASE 2.5 ni bajar: SaaS schema.
docs/06-SAAS.md §2 aynan bajarilsin.
- plans (code, name_ru, name_uz, days, price, is_active, sort_order)
  Seed: MONTHLY 30, QUARTERLY 90, YEARLY 365 — narx 0
- subscriptions (status: ACTIVE|EXPIRING|EXPIRED|SUSPENDED|TRIAL)
- subscription_payments — plan_code/name_ru/name_uz/days SNAPSHOT ustunlari bilan.
  MUHIM: to'lov tarixi plans jadvaliga JOIN qilmasin, snapshot ishlatsin
- restaurants ustunlari + deleted_at (SoftDeletes)
- users.role: SUPER_ADMIN | OWNER_ADMIN | ADMIN | WAITER
  Har restoranda aynan bitta OWNER_ADMIN — generated column + UNIQUE
- settings.restaurant_id nullable, UNIQUE(restaurant_id, key)
- SUPER_ADMIN seeder (restaurant_id = null)
Faqat PHASE 2.5.
```

---

## PHASE 3 — CUSTOMER PWA (menyu qismi)

**Vazifa:** NFC orqali kirish, guest count, menyu, cart. Hali order yuborilmaydi.

**Natija:**
- `/t/{nfc_token}` sahifasi: stol raqami + "Necha kishi?" [-] N [+]
- Menyu: kategoriya navigatsiyasi, mahsulot kartochkasi (rasm, nom, tavsif, og'irlik, narx)
- Sticky cart, miqdor o'zgartirish, qidiruv
- `is_available = false` mahsulot buyurtma qilinmaydi
- Til almashtirish tugmasi (UZ/RU)
- PWA: manifest, service worker, ikonkalar, offline shell
- Cart localStorage'da saqlanadi

**Qabul mezoni:** telefonda NFC/URL orqali ochiladi, ikkala tilda to'g'ri ishlaydi, cart hisob-kitobi to'g'ri.

**Prompt:**
```
PHASE 3 ni bajar: Customer PWA (menyu va cart).
- GET /api/v1/t/{nfc_token} → stol ma'lumoti
- GET /api/v1/menu → kategoriyalar + mahsulotlar (Accept-Language ga qarab ru/uz)
- Frontend: guest count ekrani → menyu → cart
- Mobile-first, katta tugmalar, sticky cart, kategoriya tab, qidiruv
- Rasm optimizatsiyasi (lazy load)
- UZ/RU almashtirish, barcha matn i18n orqali
- PWA: manifest + service worker + offline shell
- Cart localStorage'da
Order yuborish HALI YO'Q — u PHASE 5 da. Faqat PHASE 3.
```

---

## PHASE 4 — SESSION TIZIMI

**Vazifa:** `SessionService` va session lifecycle.

**Natija:**
- `POST /sessions` — guest_count bilan session ochish
- `customer_session_token` (random, xavfsiz) — cookie/localStorage
- Table status ↔ session status sinxron
- NFC kirishda logika: ACTIVE → ulanadi | WAITING_PAYMENT → menyu ochiladi, submit bloklanadi | CLOSED → yangi session
- Unit testlar

**Qabul mezoni:** 3 ta holat ham test bilan qoplangan. `session_id=501` kabi predictable ID ishlatilmagan.

**Prompt:**
```
PHASE 4 ni bajar: session tizimi.
- SessionService: openSession, findActiveSession, hasUnpaidSession, closeSession
- POST /api/v1/sessions (guest_count) → customer_session_token qaytaradi (random 64 belgi)
- GET /api/v1/sessions/{token} → session + orderlar
- docs/01-ARCHITECTURE.md §12 dagi NFC kirish logikasi to'liq
- Table status va Session status sinxronizatsiyasi
- Concurrency: bir stolga ikki telefon kirsa ham bitta session (transaction + lock)
- SessionService uchun unit testlar
Faqat PHASE 4.
```

---

## PHASE 5 — ORDER TIZIMI

**Vazifa:** Order yaratish, lock, idempotency, narx qayta hisoblash.

**Natija:**
- `POST /orders` — `client_order_uuid` bilan, transaction ichida (§8)
- Narx DB'dan qayta hisoblanadi
- Yetkazilmagan order bo'lsa → block + xabar
- WAITING_PAYMENT bo'lsa → draft saqlanadi + xabar
- `order_items` snapshot (ru+uz nom, narx)
- Customer order status ekrani
- Status transition validatsiyasi (§3)

**Qabul mezoni:** tugmani 2 marta bosganda 1 ta order. Frontenddan soxta narx yuborilsa e'tiborga olinmaydi.

**Prompt:**
```
PHASE 5 ni bajar: order tizimi.
- OrderService: createOrder (docs §8 dagi transaction aynan), changeStatus (docs §3 transition matritsasi)
- POST /api/v1/orders — client_order_uuid idempotency (unique index)
- Narx faqat DB'dan; frontend yuborgan price butunlay e'tiborsiz qoldiriladi
- order_items: product_name_ru_snapshot, product_name_uz_snapshot, price_snapshot
- Order lock: yetkazilmagan order bo'lsa 409 ORDER_NOT_DELIVERED
- WAITING_PAYMENT bo'lsa: order draft (session_id=null) + 409 SESSION_WAITING_PAYMENT
- Customer order status ekrani (Yuborildi → Qabul qilindi → Biriktirildi → Yetkazilmoqda → Yetkazildi)
  DIQQAT: "Kitchen accepted" kabi status BO'LMASIN
- Feature testlar: idempotency, narx manipulyatsiyasi, order lock
Faqat PHASE 5.
```

---

## PHASE 6 — ADMIN PANEL

**Vazifa:** Dashboard, stollar, buyurtmalar, menyu boshqaruvi.

**Natija:**
- Login (email/parol, Sanctum)
- Dashboard: bugungi daromad / buyurtma / mijoz / o'rtacha chek
- Stollar grid + rang bilan status
- Stol kartochkasi: raqam, kishi soni, status, order, total, waiter
- Yangi order kartochkasi + "Qabul qilish"
- Menyu CRUD (kategoriya + mahsulot, ikki tilda majburiy)
- Stol CRUD + NFC URL/token generatsiya
- Afitsant CRUD
- Sidebar (docs bo'yicha)

**Qabul mezoni:** admin order qabul qila oladi, menyu boshqara oladi, ikki tilda ishlaydi.

**Prompt:**
```
PHASE 6 ni bajar: Admin panel.
- Sanctum auth (admin login)
- Dashboard: bugungi daromad, buyurtmalar soni, mijozlar, o'rtacha chek + stollar grid (rangli status)
- Buyurtmalar sahifasi: yangi order kartochkasi, [QABUL QILISH] tugmasi
  POST /api/v1/admin/orders/{id}/accept (PENDING → ACCEPTED, transaction)
- Menyu boshqaruvi: kategoriya va mahsulot CRUD.
  Mahsulot formasida name_uz VA name_ru ikkalasi ham majburiy maydon
  Rasm yuklash: MIME va hajm validatsiyasi, storage/products/ ga, DB'da faqat URL
- Stollar CRUD + nfc_token avtomatik generatsiya + NFC URL ko'rsatish
- Afitsantlar CRUD
- Policy orqali authorization
- Sidebar: Dashboard, Buyurtmalar, Stollar, Menyu, Afitsantlar, To'lovlar, Hisobotlar, Xarajatlar, Bildirishnomalar, Sozlamalar
Real-time HALI YO'Q (PHASE 9). Faqat PHASE 6.
```

---

## PHASE 6.5 — OBUNA NAZORATI (SUBSCRIPTION MIDDLEWARE)

**Vazifa:** `docs/06-SAAS.md` §3, §4, §5 — obuna holati va bloklash.

**Natija:**
- `CheckSubscription` middleware: `/admin/*`, `/waiter/*`, `/t/*`
  Istisno: `/auth/login`, `/admin/subscription`, `/super/*`
- Kunlik scheduler: `expires_at` bo'yicha ACTIVE → EXPIRING (≤5 kun) → EXPIRED
- EXPIRED holati (§4 jadvali aynan):
  customer "Restoran vaqtincha ishlamayapti" · waiter login qila olmaydi ·
  admin faqat "To'lov" sahifasini ko'radi
- **Grace period 3 kun** — admin panel read-only (hisobot yuklab olish uchun)
- Admin panelda "To'lov" sahifasi: holat, tariflar (`plans` dan),
  aloqa ma'lumotlari (`settings` dan) — hech biri hardcode emas
- `POST /admin/subscription/request` — so'rov yuborish (to'lov emas)

**Qabul mezoni:** EXPIRED restoranda 3 ta rol ham §4 jadvalidagidek xatti-harakat
qiladi. Ma'lumot **o'chirilmaydi**. To'langach hammasi qaytadi.

**Prompt:**
```
PHASE 6.5 ni bajar: obuna nazorati.
docs/06-SAAS.md §3, §4, §5 aynan.
- SubscriptionService: holat hisoblash, expires_at bo'yicha status
- CheckSubscription middleware + istisno route'lar
- Kunlik scheduler command (cPanel cron bilan yuriladi)
- Grace period 3 kun: EXPIRED bo'lgach admin panel read-only
- Admin "To'lov" sahifasi: plans va settings dan o'qiydi, hardcode YO'Q
- POST /admin/subscription/request
- Feature testlar: TRIAL tugashi, EXPIRING, EXPIRED, grace period,
  har rol uchun bloklash
Faqat PHASE 6.5.
```

---

## PHASE 7 — WAITER PWA

**Vazifa:** Afitsant paneli.

**Natija:**
- Login (telefon/username + parol yoki PIN)
- Status ko'rsatkichi: 🟢 BO'SH / 🔴 BAND
- Yangi order kartochkasi + "Qabul qilish"
- "Yetkazildi" tugmasi
- Chaqiruvlar ro'yxati
- Tarix, profil
- PWA + push notification tayyorligi

**Qabul mezoni:** waiter faqat o'ziga assign qilingan orderni ko'radi va o'zgartiradi.

**Prompt:**
```
PHASE 7 ni bajar: Waiter PWA.
- Auth: username/telefon + parol yoki PIN
- Bosh ekran: "Salom, {ism}" + status 🟢 BO'SH / 🔴 BAND
- GET /api/v1/waiter/orders — faqat menga assign qilinganlar
- POST /waiter/orders/{id}/accept → WAITER_ACCEPTED, waiter BUSY
- POST /waiter/orders/{id}/deliver → DELIVERED, waiter FREE
- Chaqiruvlar: qabul qilish / bajarildi
- Sidebar: Asosiy, Mening buyurtmalarim, Chaqiruvlar, Tarix, Profil
- Policy: waiter boshqa waiterning orderini KO'RA VA O'ZGARTIRA OLMASIN (test bilan)
- PWA: manifest + service worker + push notification tayyorligi
- UZ/RU
Faqat PHASE 7.
```

---

## PHASE 8 — AVTOMATIK AFITSANT BIRIKTIRISH

**Vazifa:** `WaiterAssignmentService`.

**Natija:** docs §7 dagi algoritm, navbat (`WAITING_FOR_WAITER`), waiter bo'shaganda avtomatik assign.

**Qabul mezoni:** unit testlar — teng yuk, navbat, bo'sh waiter yo'q holati.

**Prompt:**
```
PHASE 8 ni bajar: avtomatik afitsant biriktirish.
- WaiterAssignmentService — docs/01-ARCHITECTURE.md §7 algoritmi aynan
- Admin ACCEPT bosgach avtomatik ishga tushadi (bitta transaction ichida)
- FREE waiter yo'q bo'lsa → order WAITING_FOR_WAITER, admin panelda "Barcha afitsantlar band" ko'rsatiladi
- Waiter DELIVERED bosgach → FREE → navbatdagi eng eski order avtomatik assign
- users.last_free_at yangilanib borsin
- Unit testlar: eng kam yuk, teng yuk (last_free_at bo'yicha), navbat, bo'sh waiter yo'q
Faqat PHASE 8.
```

---

## PHASE 9 — REAL-TIME WEBSOCKET

**Vazifa:** Laravel Reverb + eventlar.

**Natija:** docs §10 dagi barcha eventlar, admin/waiter/customer refreshsiz yangilanadi.

**Qabul mezoni:** ikki brauzer ochib tekshirilgan — admin sahifasi refreshsiz yangilanadi.

**Prompt:**
```
PHASE 9 ni bajar: real-time.
- Laravel Reverb sozlash
- Kanallar: private-restaurant.{id}, private-waiter.{userId}, public-table.{nfc_token}
- docs/01-ARCHITECTURE.md §10 dagi barcha eventlar
- Admin dashboard refreshsiz: yangi order → stol qizil, waiter accept → ism ko'rinadi,
  delivered → status, paid → AVAILABLE
- Waiter: yangi order darhol chiqadi
- Customer: order status jonli yangilanadi
- Reconnect logikasi (uzilib qolsa qayta ulanish)
Faqat PHASE 9.
```

---

## PHASE 10 — OVOZLI BILDIRISHNOMA

**Vazifa:** Admin uchun Web Speech API, ru/uz.

**Natija:** docs §11 + i18n §8 (tartib sonlar lug'ati), takrorlanmaslik.

**Qabul mezoni:** sahifa refresh qilinganda eski xabarlar qayta o'qilmaydi.

**Prompt:**
```
PHASE 10 ni bajar: ovozli bildirishnoma.
- Admin panelda Web Speech API
- docs/02-I18N-RU-UZ.md §8: 1-50 tartib sonlar lug'ati ru va uz uchun
- Shablon UZ: "{ordinal} stoldan yangi buyurtma bor" / "{ordinal} stoldan chaqruv bor"
- Shablon RU: "Новый заказ со {ordinal} стола" / "Вызов с {ordinal} стола"
- MUHIM: bir event = bir marta ovoz. notifications.voice_played flag.
  Sahifa refresh bo'lsa eski notification QAYTA O'QILMASIN.
- Admin sozlamalarida: ovozni yoqish/o'chirish, tovush balandligi, til
Faqat PHASE 10.
```

---

## PHASE 11 — AFITSANT CHAQIRUVI

**Vazifa:** Waiter call oqimi.

**Prompt:**
```
PHASE 11 ni bajar: afitsant chaqiruvi.
- Customer PWA'da doimiy tugma: "Afitsantni chaqirish"
- POST /api/v1/waiter-calls → waiter_calls yozuvi (PENDING)
- Admin panelda real-time + ovoz: "Ikkinchi stoldan chaqruv bor"
- Waiter panelda ham ko'rinadi → [QABUL QILISH] → [BAJARILDI]
- Statuslar: PENDING → ASSIGNED → ACCEPTED → COMPLETED
- Spam himoyasi: bir stoldan 2 daqiqada 1 marta (rate limit)
Faqat PHASE 11.
```

---

## PHASE 12 — TO'LOV VA SESSION YOPISH

**Vazifa:** `PaymentService`, eng kritik edge case.

**Natija:**
- Admin: session bo'yicha barcha orderlar + TOTAL
- To'lov usuli: NAQD / KARTA / BOSHQA → [TO'LANDI]
- Session CLOSED, Table AVAILABLE
- **Draft order avtomatik yangi sessionga o'tadi + narx qayta hisoblanadi**
- Majburiy yopish (confirmation + audit log)

**Qabul mezoni:** docs §12 edge case to'liq ishlaydi.

**Prompt:**
```
PHASE 12 ni bajar: to'lov va session yopish.
- PaymentService
- Admin: stol bo'yicha session detali — barcha orderlar, TOTAL
- To'lov usuli tanlash (CASH/CARD/OTHER) → [TO'LANDI]
- Transaction: payment PAID → session PAID → CLOSED → table AVAILABLE
- MUHIM (docs §12): session yopilgach, shu stolda kutayotgan draft order bo'lsa —
  yangi session yaratiladi, draft order shunga biriktiriladi,
  NARXLAR DB'DAN QAYTA OLINADI, keyin order flow boshlanadi
- Majburiy yopish: [SESSIONNI MAJBURAN YOPISH] + confirmation modal + activity_logs ga yozish
- Feature testlar: to'liq to'lov oqimi + draft order chiqishi
Faqat PHASE 12.
```

---

## PHASE 13 — HISOBOTLAR VA XARAJATLAR

**Prompt:**
```
PHASE 13 ni bajar: hisobotlar.
- ReportService: docs/01-ARCHITECTURE.md §14 formulalari
- Davrlar: Bugun, Kecha, 7 kun, 30 kun, Custom (sana oralig'i)
- Ko'rsatkichlar: Revenue, Orders, Guests, Average Check, Products Sold,
  Paid/Unpaid sessions, Cancelled, Discounts, Expenses, Net Profit
- Eng ko'p sotilgan mahsulotlar TOP-10
- Afitsantlar statistikasi: active orders, bugungi orderlar, delivered, chaqiruvlar
- Xarajatlar CRUD
- Grafik (chart) bilan vizualizatsiya
- Ikki tilda
Faqat PHASE 13.
```

---

## PHASE 13.5 — SUPER ADMIN PANELI

**Vazifa:** `docs/06-SAAS.md` §9 va §11 — platforma boshqaruvi.

**Natija:**
- Alohida frontend app: `frontend/super/` → `super.itcode.uz`
- Dashboard: jami/faol/tugayapti/tugagan restoran, bu oy tushum
- Restoranlar ro'yxati + kartochka (Ma'lumot / Obuna / To'lov tarixi /
  Limitlar / Statistika)
- **Yangi restoran yaratish:** restoran + OWNER_ADMIN useri + N ta stol +
  TRIAL 7 kun. **Demo kategoriya/mahsulot QO'YILMAYDI** (§9)
- Obunani faollashtirish: `expires_at = max(now, eski expires_at) + plan.days`
- `plans` CRUD — nom (ru+uz), kun, narx, faol/nofaol
- Limitlar (`max_tables` / `max_products` / `max_waiters`) har restoran uchun
- Platforma `settings`: `contact_phone`, `contact_telegram`, `contact_note_*`
- **Arxivlash** (soft delete) va **butunlay o'chirish** (§11) —
  nomini yozib tasdiqlash bilan
- "Admin sifatida kirish" (impersonation) → `activity_logs`

**Qabul mezoni:** super admin restoran yarata, obunani uzaytira, arxivlay va
tiklay oladi. Butunlay o'chirish faqat arxivlangan restoran uchun ishlaydi.

**Prompt:**
```
PHASE 13.5 ni bajar: SUPER ADMIN paneli.
docs/06-SAAS.md §9 va §11 aynan.
- frontend/super/ — yangi Vite app
- /api/v1/super/* route'lar, faqat SUPER_ADMIN
- restaurant_id global scope faqat /super/* da chetlab o'tiladi, boshqa joyda YO'Q
- Yangi restoran: restoran + OWNER_ADMIN + stollar + TRIAL 7 kun
  DEMO KATEGORIYA/MAHSULOT QO'YMA — bo'sh menyu (javob 8)
- Obuna faollashtirish: expires_at = max(now, eski expires_at) + plan.days
  subscription_payments ga SNAPSHOT bilan yozuv
- plans CRUD, limitlar, platforma settings
- Arxivlash + butunlay o'chirish (nom yozib tasdiqlash, rasmlar ham o'chadi)
- Impersonation + activity_logs
- Testlar: obuna uzaytirish formulasi, arxiv/tiklash, hard delete cheklovi
Faqat PHASE 13.5.
```

---

## PHASE 13.6 — XABARNOMALAR VA TELEGRAM

**Vazifa:** `docs/06-SAAS.md` §6.

**Natija:**
- Kunlik scheduler: 5, 4, 3, 2, 1 kun qolganda va tugagan kuni xabar
- Restoran adminiga: admin panelda **yopib bo'lmaydigan banner** + Telegram
- SUPER_ADMIN ga: super panelda ro'yxat + Telegram kunlik xulosa
- **Telegram bot yangi yaratiladi**, `.env` → `TELEGRAM_BOT_TOKEN`
- Admin panelda "Telegramni ulash" → deep link `/start <token>` →
  `restaurants.owner_telegram_chat_id`
- Matnlar `lang/{ru,uz}/` da — hardcode yo'q

**Qabul mezoni:** obuna tugashiga 3 kun qolganda admin banner ko'radi va
Telegram xabar keladi. Bir kun uchun bir marta yuboriladi (takror emas).

**Prompt:**
```
PHASE 13.6 ni bajar: xabarnomalar va Telegram.
docs/06-SAAS.md §6 aynan.
- TelegramService — yangi bot, TELEGRAM_BOT_TOKEN .env dan
- "Telegramni ulash": deep link /start <token> -> chat_id saqlanadi
- Kunlik scheduler: 5,4,3,2,1 kun va tugagan kuni
  MUHIM: bir kun uchun bir marta (takrorlanmasin)
- Admin panelda yopib bo'lmaydigan banner
- SUPER_ADMIN ga kunlik xulosa
- Barcha matn lang/ru va lang/uz da
- Queue: database driver (Redis yo'q), cron bilan yuriladi
Faqat PHASE 13.6.
```

---

## PHASE 14 — XAVFSIZLIK VA LOG

**Prompt:**
```
PHASE 14 ni bajar: xavfsizlik.
- docs/01-ARCHITECTURE.md §13 dagi barcha punktlar
- Rate limiting: order submit, waiter call, login
- Barcha Request klasslarida validation (ru+uz xabar)
- Policy'lar to'liq: waiter cheklovlari, restaurant izolyatsiyasi
- Multi-tenant test: Restoran A Restoran B ma'lumotini KO'RA OLMASLIGI test bilan isbotlansin
- activity_logs: order/payment/product price/table o'zgarishlari (old_values, new_values, ip)
- Fayl yuklash: MIME, hajm, kengaytma validatsiyasi
- Customer price/total/restaurant_id/waiter_id/status yubora olmasligi test bilan
Faqat PHASE 14.
```

**SaaS qo'shimchasi (docs/06-SAAS.md §10):** multi-tenant izolyatsiya testi
**har bir endpoint bo'yicha** — Restoran A admini Restoran B ning
order / product / table / report / payment / waiter'iga murojaat qilsin →
hammasi 403/404. Data-provider bilan barcha admin endpointi qoplansin.
Qo'shimcha: fayl yo'llari `storage/products/{restaurant_id}/...`,
broadcast kanal auth'ida restoran tekshiruvi, `settings` da
`restaurant_id IS NULL` istisnosi boshqa jadvalga tarqalmasligi.

---

## PHASE 15 — TESTING (KRITIK)

**Prompt:**
```
PHASE 15 ni bajar: to'liq testing.
- Unit: SessionService, OrderService, WaiterAssignmentService, PaymentService, ReportService
- Feature: docs/04-TEST-SCENARIO.md dagi 30 qadamli kritik scenario TO'LIQ
- i18n testi: ru.json va uz.json kalitlari 1:1 mos
- Concurrency testi: bir stolga bir vaqtda ikki so'rov
- Idempotency testi
Barcha testlar yashil bo'lmaguncha to'xtama.
```

---

## PHASE 16 — PRODUCTION OPTIMIZATSIYA

**Prompt:**
```
PHASE 16 ni bajar: production tayyorlik.
- Query optimizatsiya (N+1 yo'qotish, eager loading, index tekshiruvi)
- Rasm optimizatsiya (resize, webp)
- Frontend build optimizatsiya (code split, lazy load)
- Redis cache: menyu, dashboard
- Queue: notification, broadcast
- Deploy yo'riqnomasi (README): server talablari, .env, migration, supervisor, nginx, SSL
- Backup strategiyasi
Faqat PHASE 16.
```

---

## PROGRESS TRACKER

| Phase | Nomi | Holat |
|---|---|---|
| 0 | Tayyorgarlik | ⬜ |
| 1 | Project setup | ⬜ |
| 2 | Database | ⬜ |
| 2.5 | SaaS schema | ⬜ |
| 3 | Customer PWA | ⬜ |
| 4 | Session tizimi | ⬜ |
| 5 | Order tizimi | ⬜ |
| 6 | Admin panel | ⬜ |
| 6.5 | Obuna nazorati | ⬜ |
| 7 | Waiter PWA | ⬜ |
| 8 | Auto assignment | ⬜ |
| 9 | Real-time | ⬜ |
| 10 | Ovozli bildirishnoma | ⬜ |
| 11 | Afitsant chaqiruvi | ⬜ |
| 12 | To'lov / session yopish | ⬜ |
| 13 | Hisobotlar | ⬜ |
| 13.5 | Super admin paneli | ⬜ |
| 13.6 | Xabarnomalar / Telegram | ⬜ |
| 14 | Xavfsizlik | ⬜ |
| 15 | Testing | ⬜ |
| 16 | Production | ⬜ |
