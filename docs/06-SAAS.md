# 06 — SaaS QATLAMI (Multi-Restaurant + Obuna)

Tizim bitta serverda ishlaydi, ko'p restoran undan foydalanadi. Har restoran o'z nomi, menyusi, stollari, afitsantlari bilan.

To'lov **onlayn emas** — qo'lda tasdiqlanadi (§5).

---

## 1. ROL IYERARXIYASI

```
SUPER_ADMIN  (platforma egasi — Bakhrullo)
     └── Restaurant A
     │      ├── ADMIN   (restoran egasi)
     │      └── WAITER
     └── Restaurant B
            ├── ADMIN
            └── WAITER
```

`users.role`: `SUPER_ADMIN` | `ADMIN` | `WAITER`

**SUPER_ADMIN** `restaurant_id = null` — u hech qaysi restoranga tegishli emas, hammasini ko'radi.

⚠️ Global scope (`restaurant_id`) SUPER_ADMIN uchun chetlab o'tilishi kerak — lekin faqat `/super/*` route'larda. Boshqa joyda hech qachon.

---

## 2. YANGI JADVALLAR

### subscriptions
```
id
restaurant_id
plan                  MONTHLY | QUARTERLY | YEARLY
status                ACTIVE | EXPIRING | EXPIRED | SUSPENDED | TRIAL
started_at
expires_at
amount                to'langan summa
activated_by          super_admin user_id
note                  "Click orqali 1 yillik, 02.09.2026"
created_at, updated_at
```

### subscription_payments (to'lov tarixi)
```
id
restaurant_id
subscription_id
plan
amount
paid_at
confirmed_by          super_admin user_id
method                CLICK | CASH | TRANSFER | OTHER
reference             chek raqami / izoh
created_at
```

### restaurants — qo'shiladigan ustunlar
```
subscription_status   ACTIVE | EXPIRING | EXPIRED | SUSPENDED | TRIAL
expires_at
owner_phone
owner_telegram
max_tables
max_products
max_waiters
suspended_reason
```

### plans (yoki config faylda)
```
id, code, name_ru, name_uz, days, price, is_active
```
Boshlang'ich: `MONTHLY 30 kun`, `QUARTERLY 90 kun`, `YEARLY 365 kun`.

---

## 3. OBUNA HOLATI (avtomatik)

Har kuni scheduler (`cron`) ishlaydi:

```
expires_at - bugun > 5 kun   → ACTIVE
expires_at - bugun ≤ 5 kun   → EXPIRING  (har kuni xabar)
expires_at < bugun           → EXPIRED   (tizim bloklanadi)
SUPER_ADMIN qo'lda           → SUSPENDED (majburiy yopish)
```

**MUHIM:** `expires_at` sana emas, aniq vaqt (`datetime`). Restoran soat 14:00 da to'lasa, keyingi yil soat 14:00 da tugaydi.

---

## 4. EXPIRED BO'LGANDA NIMA BO'LADI

| Kim | Natija |
|---|---|
| Customer (NFC) | Sahifa ochiladi, lekin: "Restoran vaqtincha ishlamayapti" |
| Waiter | Login qila olmaydi |
| Admin | Login qiladi, **faqat "To'lov" sahifasini** ko'radi |
| Ma'lumot | O'chirilmaydi — hammasi joyida turadi |

**Ma'lumot hech qachon o'chirilmasin.** To'langach hammasi qaytadi.

**Grace period:** EXPIRED bo'lgach 3 kun davomida admin panel read-only ishlaydi (hisobotni ko'rish, yuklab olish uchun). 3 kundan keyin to'liq bloklanadi.

Middleware: `CheckSubscription` — barcha `/api/v1/admin/*`, `/waiter/*`, `/t/*` route'larda.
Bundan mustasno: `/auth/login`, `/admin/subscription`, `/super/*`.

---

## 5. TO'LOV OQIMI (qo'lda)

### Restoran egasi tomonidan

Admin panel → **To'lov** sahifasi:

```
OBUNA HOLATI
Restoran:      Osiyo Milliy Taomlari
Holat:         🟡 5 kun qoldi
Tugash sanasi: 07.09.2026 14:00

TARIFNI TANLANG
┌──────────────────────────────┐
│ 1 OYLIK       xxx xxx so'm   │
│ 3 OYLIK       xxx xxx so'm   │
│ 1 YILLIK      xxx xxx so'm   │  ← eng foydali
└──────────────────────────────┘

TO'LOV UCHUN BOG'LANING
📞 +998 XX XXX XX XX
✈️  @telegram_username

1. Yuqoridagi raqamga qo'ng'iroq qiling
2. Tarifni ayting
3. Click orqali kartaga to'lov qiling
4. To'lov tasdiqlangach tizim avtomatik ochiladi
```

Tugma bosilganda **so'rov yuboriladi** (`POST /admin/subscription/request`) — SUPER_ADMIN panelida "Kutilayotgan so'rovlar" ro'yxatiga tushadi. Bu shunchaki bildirishnoma, to'lov emas.

### SUPER_ADMIN tomonidan

Super panel → Restoranlar → **Osiyo Milliy Taomlari**:

```
Holat:        🟡 EXPIRING (5 kun)
Tugaydi:      07.09.2026 14:00
So'rov:       1 YILLIK (yuborilgan 02.09.2026)

[ OBUNANI FAOLLASHTIRISH ]
  Tarif:    [1 yillik ▾]
  Summa:    [___________]
  Usul:     [Click ▾]
  Izoh:     [___________]
  [ TASDIQLASH ]
```

Tasdiqlangach:
```
expires_at = max(bugun, eski expires_at) + plan.days
status = ACTIVE
subscription_payments ga yozuv
activity_logs ga yozuv
Restoran adminiga xabar
```

⚠️ `max(bugun, eski expires_at)` — muddat tugamasdan to'lasa, qolgan kunlar yo'qolmaydi.

---

## 6. XABARNOMALAR

Har kuni scheduler yuboradi (5, 4, 3, 2, 1 kun qolganda va tugagan kuni):

**Restoran adminiga:**
- Admin panelda banner (yopib bo'lmaydigan)
- Telegram bot orqali xabar (`owner_telegram`)

**SUPER_ADMIN ga:**
- Super panelda ro'yxat: qaysi restoran, necha kun qoldi
- Telegram bot orqali kunlik xulosa

Matn (UZ):
```
⚠️ Obuna tugashiga 3 kun qoldi
Restoran: Osiyo Milliy Taomlari
Tugash sanasi: 07.09.2026
To'lov uchun: +998 XX XXX XX XX
```

RU varianti ham `lang/` da bo'lsin.

**Telegram bot** — eng oddiy yechim: bitta bot, `chat_id` `restaurants.owner_telegram_chat_id` da saqlanadi. Admin panelda "Telegramni ulash" tugmasi → bot linki → `/start <token>`.

---

## 7. DOMEN STRATEGIYASI

Har restoranga `slug` beriladi (`osiyo`, `bellissimo`).

```
menu.itcode.uz/r/osiyo/t/{nfc_token}   → Customer PWA
admin.itcode.uz                         → Admin (login orqali restoran aniqlanadi)
afitsant.itcode.uz                      → Waiter
super.itcode.uz                         → SUPER_ADMIN
api.itcode.uz                           → Laravel API
```

**Nega subdomen emas, `/r/{slug}`?** cPanel'da har restoranga subdomen qo'shish qo'lda ish. `nfc_token` baribir unique — slug faqat ko'rinish uchun.

Kelajakda restoran o'z domenini ulashi mumkin (`restaurants.custom_domain`).

---

## 8. LIMITLAR

Tarifga emas, restoranga biriktiriladi (SUPER_ADMIN o'zgartiradi):

```
max_tables     default 30
max_products   default 100
max_waiters    default 10
```

Limitdan oshsa: `422 LIMIT_EXCEEDED` + "Limitga yetdingiz. Kengaytirish uchun bog'laning."

---

## 9. SUPER ADMIN PANELI

```
DASHBOARD
  Jami restoran:      12
  Faol:               10
  Tugayapti (5 kun):   1
  Tugagan:             1
  Bu oy tushum:   x xxx xxx so'm

RESTORANLAR
  Nomi | Egasi | Holat | Tugaydi | Stollar | Bugungi order

  [ + YANGI RESTORAN ]

RESTORAN KARTOCHKASI
  Ma'lumot / Obuna / To'lov tarixi / Limitlar / Statistika
  [ FAOLLASHTIRISH ] [ TO'XTATISH ] [ ADMIN SIFATIDA KIRISH ]

TO'LOVLAR
  Barcha to'lovlar tarixi, filtr, jami summa

SO'ROVLAR
  Kutilayotgan tarif so'rovlari
```

**Yangi restoran yaratish** bitta amalda: restoran + admin useri + N ta stol (nfc_token bilan) + demo kategoriya/mahsulot + TRIAL obuna (7 kun).

**"Admin sifatida kirish"** (impersonation) — muammoni tekshirish uchun. Har safar `activity_logs` ga yoziladi.

---

## 10. XAVFSIZLIK — ENG MUHIM QISM

Bu SaaS'ning eng katta xavfi: **bir restoran boshqasining ma'lumotini ko'rishi**.

Majburiy:
1. Barcha model'da `BelongsToRestaurant` global scope
2. `restaurant_id` **hech qachon** request'dan olinmaydi — faqat `auth()->user()->restaurant_id` yoki `nfc_token` orqali
3. Har bir Policy'da restoran tekshiruvi
4. Fayl yo'llari: `storage/products/{restaurant_id}/...`
5. Broadcast kanallari: `private-restaurant.{id}` — auth'da restoran tekshiriladi

**Test majburiy:** Restoran A admini Restoran B ning order/product/table/report'iga har bir endpoint bo'yicha murojaat qilib ko'rsin → hammasi 403/404.

---

## 11. YANGI PHASE'LAR

`docs/03-PHASES.md` ga qo'shiladi:

### PHASE 2.5 — SaaS schema (PHASE 2 ichida)
`subscriptions`, `subscription_payments`, `plans` jadvallari + `restaurants` ustunlari + `SUPER_ADMIN` roli.
> Bu PHASE 2 bilan birga qilinsin — keyin qo'shish migration'larni chalkashtiradi.

### PHASE 6.5 — Subscription middleware
`CheckSubscription` middleware, EXPIRED holati, grace period, admin panelda "To'lov" sahifasi.

### PHASE 13.5 — SUPER ADMIN paneli
`super.itcode.uz`, restoranlar CRUD, obuna faollashtirish, to'lov tarixi, dashboard, impersonation.

### PHASE 13.6 — Xabarnomalar
Scheduler (kunlik holat tekshiruvi), Telegram bot, banner, 5 kunlik ogohlantirish.

### PHASE 14 ga qo'shimcha
Multi-tenant izolyatsiya testi — har bir endpoint bo'yicha.

---

## 12. SAVOLLAR (javob kerak)

1. Tarif narxlari qancha? (1 oy / 3 oy / 1 yil)
2. Yangi restoranga trial beriladimi? Necha kun?
3. Telegram bot bormi yoki yangi yaratiladimi?
4. Restoran o'z admin userini o'zi qo'sha oladimi (bir nechta admin) yoki faqat bittami?
5. Restoran o'chirilganda ma'lumot butunlay o'chsinmi yoki arxivda qolsinmi?
