# 06 — SaaS QATLAMI (Multi-Restaurant + Obuna)

Tizim bitta serverda ishlaydi, ko'p restoran undan foydalanadi. Har restoran o'z nomi, menyusi, stollari, afitsantlari bilan.

To'lov **onlayn emas** — qo'lda tasdiqlanadi (§5).

---

## 1. ROL IYERARXIYASI

```
SUPER_ADMIN  (platforma egasi — Bakhrullo)
     └── Restaurant A
     │      ├── OWNER_ADMIN  (restoran egasi — birinchi admin)
     │      ├── ADMIN        (egasi qo'shgan qo'shimcha adminlar)
     │      └── WAITER
     └── Restaurant B
            ├── OWNER_ADMIN
            ├── ADMIN
            └── WAITER
```

`users.role`: `SUPER_ADMIN` | `OWNER_ADMIN` | `ADMIN` | `WAITER`

### OWNER_ADMIN (javob 4)

Bitta restoranda **bir nechta ADMIN** bo'lishi mumkin — restoran egasi ularni
o'z admin panelidan qo'shadi.

Birinchi admin — **`OWNER_ADMIN`**:

| Qoida | |
|---|---|
| O'chirish | ❌ mumkin emas (o'zi ham, boshqa admin ham) |
| Rolini o'zgartirish | faqat SUPER_ADMIN |
| Boshqa admin qo'shish/o'chirish | ✅ |
| ADMIN o'zini OWNER_ADMIN qila oladimi | ❌ |

> Sabab: restoran o'zini o'zi qulflab qo'yishidan himoya. Har restoranda
> **doim aynan bitta** OWNER_ADMIN bo'ladi — DB darajasida
> `UNIQUE(restaurant_id, role)` `role = 'OWNER_ADMIN'` uchun ta'minlanadi
> (generated column orqali, `table_sessions.active_key` bilan bir xil usul).

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
plan_id               FK plans — ON DELETE RESTRICT
amount                TO'LANGAN SUMMA — snapshot, hech qachon o'zgarmaydi
plan_code_snapshot    MONTHLY / QUARTERLY / YEARLY
plan_name_ru_snapshot
plan_name_uz_snapshot
plan_days_snapshot    o'sha paytdagi kun soni
paid_at
confirmed_by          super_admin user_id
method                CLICK | CASH | TRANSFER | OTHER
reference             chek raqami / izoh
created_at
```

⚠️ **Snapshot ustunlari majburiy** (javob 1). Super admin tarif narxini yoki
nomini o'zgartirsa, o'tgan to'lovlar tarixi va moliyaviy hisobot
**buzilmasligi kerak**. `plans` jadvaliga JOIN qilib summa ko'rsatish
taqiqlanadi — har doim snapshot ishlatiladi.

### restaurants — qo'shiladigan ustunlar
```
slug                      unique — §7 domen strategiyasi
subscription_status       ACTIVE | EXPIRING | EXPIRED | SUSPENDED | TRIAL
expires_at                DATETIME (sana emas — §3)
owner_phone               restoran egasining raqami (SUPER_ADMIN qo'ng'iroq qiladi)
owner_telegram            @username
owner_telegram_chat_id    "Telegramni ulash" bosilgach to'ladi (§6, javob 3)
logo                      restoran egasi o'zi yuklaydi (javob 8)
max_tables                default 30
max_products              default 100
max_waiters               default 10
suspended_reason
deleted_at                ARXIVLASH — soft delete (§11, javob 5)
```

> `owner_phone` / `owner_telegram` — bu **restoran egasining** aloqasi,
> SUPER_ADMIN u bilan bog'lanishi uchun. Restoranga ko'rsatiladigan
> **platformaning** aloqa ma'lumotlari boshqa joyda — §12 ga qarang.

### plans (javob 1)

**Narxlar kodda YOZILMAYDI** — faqat DB'da, SUPER_ADMIN panelidan tahrirlanadi.

```
id, code, name_ru, name_uz, days, price, is_active, sort_order
created_at, updated_at
```

Tahrirlanadigan maydonlar: **nom (ru+uz), kun soni, narx, faol/nofaol**.

Boshlang'ich seed — narx `0`, super admin keyin kiritadi:

| code | days | price |
|---|---|---|
| `MONTHLY` | 30 | 0 |
| `QUARTERLY` | 90 | 0 |
| `YEARLY` | 365 | 0 |

⚠️ **Narx o'zgarganda eski to'lovlar o'zgarmaydi** — `subscription_payments`
to'langan summani **snapshot** sifatida saqlaydi (quyida).
Bu `order_items.price_snapshot` bilan bir xil printsip (CLAUDE.md §3.3).

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

### TRIAL (javob 2)

Yangi restoran yaratilganda **avtomatik**:

```
subscription_status = TRIAL
expires_at          = now() + 7 kun
```

Trial tugagach **alohida oqim yo'q** — oddiy `EXPIRED` holatiga o'tadi va §4
dagi qoidalar aynan ishlaydi (grace period ham). Trial faqat boshlanish
usuli, alohida holat emas.

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
📞 {settings.contact_phone}
✈️  {settings.contact_telegram}
   {settings.contact_note}          ← ru yoki uz, tilga qarab

1. Yuqoridagi raqamga qo'ng'iroq qiling
2. Tarifni ayting
3. Click orqali kartaga to'lov qiling
4. To'lov tasdiqlangach tizim avtomatik ochiladi
```

⚠️ Bu maydonlar **hardcode qilinmaydi** (javob 6) — `settings` jadvalidan
o'qiladi, SUPER_ADMIN panelidan o'zgartiriladi. §12 ga qarang.
Tariflar ham `plans` dan keladi, kodda yozilmaydi (javob 1).

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

**Tasdiqlandi (javob 7):** limit **tarifga bog'liq emas**. Uzoq muddatli tarif
= arzonroq narx, limit farqi **yo'q**. Har restoran uchun SUPER_ADMIN alohida
o'zgartiradi.

Xato matni `lang/{ru,uz}/errors.php` da (`LIMIT_EXCEEDED`), aloqa ma'lumoti
§12 dagi `settings` dan qo'shiladi.

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

**Yangi restoran yaratish** bitta amalda:

```
restoran yozuvi (nom, slug, telefon, timezone)
+ OWNER_ADMIN useri (login + boshlang'ich parol)
+ N ta stol (nfc_token bilan)
+ TRIAL obuna (7 kun)
```

⚠️ **DEMO KATEGORIYA/MAHSULOT QO'YILMAYDI** (javob 8).
Restoran **bo'sh menyu** bilan boshlanadi.

Nima kim tomonidan kiritiladi:

| Nima | Kim |
|---|---|
| Restoran yozuvi, admin useri, stollar, limitlar, obuna | SUPER_ADMIN |
| Restoran **nomi va logotipi** | restoran egasi |
| Kategoriya, mahsulot, rasm, narx | restoran egasi |

> `docs/03-PHASES.md` PHASE 2 dagi 5 kategoriya + 25 mahsulot seeder
> **faqat local development/demo** uchun qoladi — yangi restoran yaratish
> oqimida ishlatilmaydi.

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

## 11. RESTORAN O'CHIRISH (javob 5)

Ikki xil amal. **Ular aralashtirilmaydi.**

### a) ARXIVLASH — soft delete, DEFAULT variant

```
restaurants.deleted_at = now()
```

| | |
|---|---|
| Ma'lumot | **qoladi** — orderlar, to'lovlar, hisobotlar hammasi joyida |
| Customer NFC | stol topilmaydi (`INVALID_TABLE`) |
| Admin / Waiter | login qila olmaydi |
| SUPER_ADMIN | ro'yxatda "Arxivlangan" filtri ostida ko'radi |
| Qayta tiklash | ✅ `deleted_at = null` — hammasi qaytadi |

Bu **oddiy holat**. SUPER_ADMIN "O'chirish" bosganda **shu** bajariladi.

### b) BUTUNLAY O'CHIRISH — hard delete

Faqat **allaqachon arxivlangan** restoran uchun mumkin. Arxivlanmagan
restoranni to'g'ridan-to'g'ri butunlay o'chirib bo'lmaydi.

O'chadi:
- barcha bog'liq DB yozuvlari (order, payment, session, product, ...)
- `storage/products/{restaurant_id}/` — **barcha rasmlar**
- restoran logotipi

Tasdiqlash — **restoran NOMINI qo'lda yozish** talab qilinadi:

```
┌───────────────────────────────────────────────┐
│  ⚠️  BUTUNLAY O'CHIRISH                        │
│                                               │
│  Bu amalni ORQAGA QAYTARIB BO'LMAYDI.         │
│  Barcha buyurtma, to'lov va rasm o'chadi.     │
│                                               │
│  Tasdiqlash uchun restoran nomini yozing:     │
│  "Osiyo Milliy Taomlari"                      │
│  ┌─────────────────────────────────────────┐  │
│  │                                         │  │
│  └─────────────────────────────────────────┘  │
│                    [ BEKOR ]  [ O'CHIRISH ]   │
└───────────────────────────────────────────────┘
```

Nom aynan mos kelmasa tugma **ishlamaydi**.

`activity_logs` ga yoziladi: kim, qachon, qaysi restoran, nechta order/
payment/product o'chdi (`old_values` da xulosa).

> ⚠️ `activity_logs.restaurant_id` o'chirilgan restoranga ishora qiladi —
> shuning uchun bu FK `ON DELETE SET NULL` bo'ladi, aks holda audit yozuvi
> ham o'chib ketadi.

---

## 12. PLATFORMA SOZLAMALARI (javob 6)

Restoranga ko'rsatiladigan **platformaning** aloqa ma'lumotlari:

| Kalit | Izoh |
|---|---|
| `contact_phone` | to'lov uchun qo'ng'iroq raqami |
| `contact_telegram` | `@username` |
| `contact_note_ru` | qo'shimcha izoh — rus tilida |
| `contact_note_uz` | qo'shimcha izoh — o'zbek tilida |

SUPER_ADMIN panelidan o'zgartiriladi. To'lov sahifasi (§5) shu qiymatlarni
ko'rsatadi — kodda hardcode qilinmaydi.

### `settings` jadvali ikki darajali

`docs/05-PHASE0-PLAN.md` da `settings` restoran darajasida rejalashtirilgan
edi (ovoz on/off, volume). Platforma sozlamalari esa hech qaysi restoranga
tegishli emas, shuning uchun:

```
settings
  id
  restaurant_id   NULLABLE  ← null = PLATFORMA darajasi
  key
  value
  UNIQUE(restaurant_id, key)
```

| `restaurant_id` | Kim o'zgartiradi | Misol |
|---|---|---|
| `null` | SUPER_ADMIN | `contact_phone`, `contact_telegram` |
| `5` | restoran admini | `voice_enabled`, `voice_volume` |

⚠️ `BelongsToRestaurant` global scope `settings` uchun **`restaurant_id IS
NULL` yozuvlarini ham** ko'rsatishi kerak, aks holda to'lov sahifasi bo'sh
qoladi. Bu yagona istisno — boshqa jadvallarda scope qat'iy.

---

## 13. YANGI PHASE'LAR

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

## 14. JAVOBLAR (2026-09-02 — yopildi)

§12 dagi 5 ta savol va qo'shimcha 3 ta qaror.

| № | Savol | Javob | Qayerda |
|---|---|---|---|
| 1 | Tarif narxlari qancha? | Kodda emas — `plans` jadvalida, SUPER_ADMIN kiritadi. Seed: MONTHLY 30 / QUARTERLY 90 / YEARLY 365 kun, narx `0`. To'langan summa snapshot | §2 |
| 2 | Trial beriladimi? | **7 kun**, avtomatik. Tugagach oddiy EXPIRED oqimi | §3 |
| 3 | Telegram bot? | **Yangi bot.** `.env` → `TELEGRAM_BOT_TOKEN`. "Telegramni ulash" → `/start <token>` → `chat_id` | §6, PHASE 13.6 |
| 4 | Bir nechta admin? | **Ha.** Birinchisi `OWNER_ADMIN` — o'chirib bo'lmaydi, faqat SUPER_ADMIN o'zgartiradi | §1 |
| 5 | O'chirish: butunlay yoki arxiv? | **Ikkalasi.** Arxivlash — default. Butunlay o'chirish faqat arxivlangan restoran uchun, nomini yozib tasdiqlash bilan | §11 |
| 6 | *(qo'shimcha)* Aloqa ma'lumotlari | `settings` da: `contact_phone`, `contact_telegram`, `contact_note` (ru+uz). SUPER_ADMIN o'zgartiradi | §12 |
| 7 | *(qo'shimcha)* Limitlar | Restoranga biriktiriladi, tarifga emas. Default 30/100/10. Uzoq tarif = arzon narx, limit farqi yo'q | §8 |
| 8 | *(qo'shimcha)* Kontentni kim kiritadi | Restoran egasi (menyu, rasm, logo, nom). SUPER_ADMIN faqat restoran + admin useri. **Demo kontent qo'yilmaydi** | §9 |

### Javoblar tufayli o'zgargan joylar

| Joy | O'zgarish |
|---|---|
| §1 | `OWNER_ADMIN` roli qo'shildi (4 ta rol) |
| §2 `plans` | "yoki config faylda" olib tashlandi — faqat DB |
| §2 `subscription_payments` | snapshot ustunlari qo'shildi |
| §2 `restaurants` | `deleted_at`, `logo`, `owner_telegram_chat_id`, `slug` |
| §5 | to'lov sahifasidagi telefon/telegram `settings` dan |
| §9 | **demo kategoriya/mahsulot olib tashlandi** — bu javob 8 bilan bevosita ziddiyat edi |
| §11, §12 | yangi bo'limlar |
