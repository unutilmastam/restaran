# 07 — DB QARORLARI (PHASE 2 + 2.5)

Migration yozishdan oldin tasdiqlangan 6 ta nuqta.
Manba: `docs/01-ARCHITECTURE.md` §5, `docs/05-PHASE0-PLAN.md` §2, `docs/06-SAAS.md` §2.

---

## 1. `restaurant_id` — jadval bo'yicha

### Qoida

`restaurant_id` **mustaqil so'raladigan har bir jadvalda** bor.
Faqat **ota-jadval orqali** yuklanadigan bolalar jadvallarda yo'q —
ular izolyatsiyani ota-jadvaldan meros oladi (`docs/01` §5 dagi
`order_items` shu printsipda).

### Ro'yxat (20 biznes jadval)

| # | Jadval | `restaurant_id` | Izoh |
|---|---|---|---|
| 1 | `restaurants` | — | **o'zi tenant** |
| 2 | `plans` | ❌ | platforma darajasi — barcha restoranlar uchun umumiy |
| 3 | `users` | ✅ **NULLABLE** | `null` = SUPER_ADMIN |
| 4 | `subscriptions` | ✅ NOT NULL | |
| 5 | `subscription_payments` | ✅ NOT NULL | |
| 6 | `settings` | ✅ **NULLABLE** | `null` = platforma sozlamasi (`docs/06` §12) |
| 7 | `tables` | ✅ NOT NULL | |
| 8 | `table_sessions` | ✅ NOT NULL | |
| 9 | `session_devices` | ❌ | `table_sessions` ning bolasi |
| 10 | `categories` | ✅ NOT NULL | |
| 11 | `products` | ✅ NOT NULL | |
| 12 | `order_counters` | ✅ NOT NULL | |
| 13 | `orders` | ✅ NOT NULL | |
| 14 | `order_items` | ❌ | `orders` ning bolasi (`docs/01` §5) |
| 15 | `payments` | ✅ NOT NULL | |
| 16 | `waiter_calls` | ✅ NOT NULL | |
| 17 | `notifications` | ✅ NOT NULL | |
| 18 | `push_subscriptions` | ❌ | `users` ning bolasi |
| 19 | `expenses` | ✅ NOT NULL | |
| 20 | `activity_logs` | ✅ **NULLABLE** | quyida — 3-istisno |

Laravel infratuzilma jadvallari (`cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`,
`personal_access_tokens`) — `restaurant_id` yo'q.

### ⚠️ NULLABLE istisnolar — 3 ta, 1 ta emas

Siz **faqat `users`** nullable bo'lsin dedingiz. Amalda **yana ikkitasi**
xuddi shu sababga ko'ra nullable bo'lishi kerak — **platforma darajasidagi
yozuv hech qaysi restoranga tegishli emas**:

| Jadval | Nega nullable | Bu yangi qarormi |
|---|---|---|
| `users` | SUPER_ADMIN | ✅ siz aytdingiz |
| `settings` | `contact_phone`, `contact_telegram`, `contact_note_*` — bular platformaniki, restoranniki emas | ✅ `docs/06-SAAS.md` §12 da allaqachon tasdiqlangan |
| `activity_logs` | ⚠️ **yangi** — quyida | ❌ men chiqardim |

**`activity_logs` nega nullable bo'lishi shart:**

1. SUPER_ADMIN amallarining bir qismida restoran **umuman yo'q**:
   `plans` narxini o'zgartirish, platforma `settings` ini tahrirlash,
   yangi restoran yaratish (yozuv yozilayotganda restoran hali yo'q).
2. `docs/06-SAAS.md` §11 — **butunlay o'chirish** audit yozuvi.
   Agar `restaurant_id` NOT NULL + `ON DELETE CASCADE` bo'lsa,
   restoran o'chganda **"kim o'chirdi" yozuvining o'zi ham o'chib ketadi**.
   FK `ON DELETE SET NULL` bo'ladi → ustun nullable bo'lishi majburiy.

Boshqa **17 ta jadvalda `restaurant_id` qat'iy NOT NULL**.

---

## 2. `BelongsToRestaurant` global scope

### Qo'llanadigan modellar (14 ta)

```
Subscription · SubscriptionPayment · Setting · Table · TableSession
Category · Product · OrderCounter · Order · Payment · WaiterCall
Notification · Expense · ActivityLog
```

**Qo'llanmaydi:**

| Model | Nega |
|---|---|
| `Restaurant` | tenantning o'zi — `/super/*` da to'liq ro'yxat kerak |
| `Plan` | platforma darajasi, barcha restoranlar ko'radi |
| `User` | ⚠️ alohida scope — quyida |
| `OrderItem`, `SessionDevice`, `PushSubscription` | ota-jadval scope'i orqali himoyalangan |

### Chetlab o'tish sharti — **faqat `/super/*`**

⚠️ **Rolga qarab chetlab o'tilmaydi.** Agar scope `role === SUPER_ADMIN`
ni tekshirsa, SUPER_ADMIN `/admin/*` ga kirganda ham hamma restoranni
ko'rardi. Shuning uchun shart **route'ga** bog'lanadi:

```
App\Support\RestaurantContext
  ├── static bool $unscoped = false        ← faqat middleware o'zgartiradi
  ├── allowCrossRestaurant()
  └── isUnscoped()

App\Http\Middleware\AllowCrossRestaurant
  → RestaurantContext::allowCrossRestaurant()
  → FAQAT `/api/v1/super/*` route guruhiga ulanadi, boshqa hech qayerga
```

Scope mantig'i:

```
1. RestaurantContext::isUnscoped()  → cheklov YO'Q      (faqat /super/*)
2. restaurant_id aniqlandi          → where restaurant_id = X
   (auth()->user()->restaurant_id  yoki  nfc_token / customer token orqali)
3. restaurant_id aniqlanmadi        → whereRaw('1 = 0')  ← BO'SH natija
```

3-qadam muhim: aniqlanmagan holatda **cheklovsiz qoldirilmaydi**, aksincha
hech nima qaytarilmaydi. Xato bo'lsa ma'lumot sizib chiqmaydi, sahifa bo'sh
qoladi — bu xavfsizroq.

**`Setting` uchun istisno:**
```
where(fn($q) => $q->where('restaurant_id', X)->orWhereNull('restaurant_id'))
```
Aks holda to'lov sahifasi aloqa ma'lumotisiz qoladi. Bu **yagona** shunday
jadval — boshqasiga tarqalmasligi PHASE 14 testi bilan qulflanadi.

**`User` uchun alohida scope:** `where('restaurant_id', X)` — `IS NULL`
qo'shilmaydi, shunda restoran admini SUPER_ADMIN hisobini **ko'rmaydi**.

---

## 3. `plans.price` o'zgarsa `subscription_payments` o'zgarmasligi

**4 qatlamli kafolat:**

**1-qatlam — snapshot ustunlari.** To'lov yozuvi `plans` ga JOIN qilmaydi:

```
amount                  DECIMAL(12,2)  to'langan summa
plan_code_snapshot      VARCHAR(32)    MONTHLY / QUARTERLY / YEARLY
plan_name_ru_snapshot   VARCHAR(120)
plan_name_uz_snapshot   VARCHAR(120)
plan_days_snapshot      SMALLINT       o'sha paytdagi kun soni
```

**2-qatlam — model darajasida o'zgarmaslik.** `SubscriptionPayment`
modelida `updating` hodisasi bloklanadi:

```php
static::updating(function (SubscriptionPayment $payment) {
    throw new RuntimeException('To\'lov yozuvi o\'zgartirilmaydi.');
});
```
To'lov yozuvi **faqat yaratiladi**, hech qachon tahrirlanmaydi.
`updated_at` ustuni ham yo'q — faqat `created_at`.

**3-qatlam — FK himoyasi.** `plan_id` → `ON DELETE RESTRICT`.
To'lovi bor tarifni o'chirib bo'lmaydi (`is_active = false` qilinadi).

**4-qatlam — test.** `PlanPriceSnapshotTest`:
to'lov yaratiladi → `plans.price` o'zgartiriladi → to'lov yozuvi va
hisobot summasi **o'zgarmaganini** tekshiradi.

> Bu `order_items.price_snapshot` bilan bir xil printsip (CLAUDE.md §3.3).

---

## 4. Cascade zanjiri

### a) ARXIVLASH — `deleted_at` (default)

```
restaurants.deleted_at = now()
```
**Hech nima o'chmaydi.** Bolalar jadvallar tegilmaydi — ular restoran
ko'rinmagani uchun baribir yetib bo'lmaydigan holatga o'tadi.
`deleted_at = null` → hammasi qaytadi.

### b) BUTUNLAY O'CHIRISH — zanjir

```
restaurants
├── users ─────────────► push_subscriptions        CASCADE
│      └── orders.waiter_id           SET NULL
│      └── payments.received_by       SET NULL
│      └── subscriptions.activated_by SET NULL
│      └── activity_logs.user_id      SET NULL
├── tables ────────────► table_sessions ──► session_devices   CASCADE
│                                       └─► orders ──► order_items
│                                       └─► payments
│                                       └─► waiter_calls
├── categories ────────► products                   CASCADE
├── orders ────────────► order_items                CASCADE
├── order_counters                                  CASCADE
├── notifications · expenses · settings             CASCADE
├── subscriptions ─────► subscription_payments      CASCADE
└── activity_logs                                   SET NULL   ← audit qoladi

plans                                               TEGILMAYDI (platforma)
```

⚠️ **`order_items.product_id` = `RESTRICT`.** Bu ataylab — mahsulotni
tasodifan o'chirib, buyurtma tarixini buzib qo'yishdan himoya.
Shu sababli butunlay o'chirish **DB cascade'iga tashlab qo'yilmaydi**:
`RestaurantPurgeService` transaction ichida **aniq tartibda** o'chiradi

```
order_items → orders → payments → session_devices → table_sessions
→ waiter_calls → products → categories → tables → order_counters
→ notifications → expenses → settings → subscription_payments
→ subscriptions → push_subscriptions → users → restaurant
```

va shundan keyin `storage/products/{restaurant_id}/` papkasini o'chiradi.
Servis PHASE 13.5 da yoziladi; FK ta'riflari **hozir** to'g'ri qo'yiladi.

---

## 5. Money — `DECIMAL(12,2) UNSIGNED`, API'da **son**

### DB
Barcha pul ustunlari: `DECIMAL(12,2) UNSIGNED`.
`FLOAT`/`DOUBLE` **hech qayerda ishlatilmaydi**.

### Muammo
Laravel'ning `decimal:2` cast'i **string** qaytaradi → JSON'da `"310000.00"`.

### Yechim — `App\Casts\Money`

```php
get: (float) $value              → JSON'da 310000  (son)
set: number_format($v, 2, '.', '')  → DB'ga aniq DECIMAL matn
```

`set` da formatlash muhim: float to'g'ridan-to'g'ri DECIMAL ustunga
yozilsa yaxlitlash chetlanishi bo'lishi mumkin.

### Qat'iy qoida
> **Pul PHP'da yig'ilmaydi.** Barcha `SUM()`, `total`, `subtotal`
> hisoblari **SQL'da** (DECIMAL arifmetikasi) yoki butun songa
> aylantirilgan holda bajariladi. `float` faqat **JSON'ga chiqarish**
> uchun ishlatiladi.

`DECIMAL(12,2)` → maksimum `9 999 999 999.99`. Double 15–16 raqamni aniq
saqlaydi, shuning uchun bu diapazonda chiqarishda yo'qotish yo'q.

Test: `MoneyCastTest` — `json_encode` natijasida qiymat **son** (string
emas) ekanini va `"310000.00"` → `310000` bo'lishini tekshiradi.

---

## 6. `active_key` generated column

**MySQL 8.0.44** (tasdiqlangan). Lokal tekshiruv MariaDB 10.11 da — ikkalasi
ham `STORED` generated column ustidagi `UNIQUE` ni qo'llab-quvvatlaydi.
`VIRTUAL` **ishlatilmaydi**: MariaDB unda `UNIQUE` ga ruxsat bermaydi.

### `table_sessions.active_key`

Maqsad: **bitta stolda bir vaqtda faqat bitta ochiq session**.

```sql
active_key BIGINT UNSIGNED
  GENERATED ALWAYS AS (
    CASE WHEN status IN ('ACTIVE','WAITING_PAYMENT') THEN table_id END
  ) STORED,
UNIQUE KEY table_sessions_active_key_unique (active_key)
```

`PAID` va `CLOSED` sessionlarda `NULL` → NULL lar unique indexda
takrorlanishi mumkin, shuning uchun bitta stolning yuzlab yopilgan
sessioni bemalol yashaydi.

Ustun **`status` va `table_id` dan keyin** e'lon qilinadi.

### `users.owner_admin_key`

Maqsad: **har restoranda aynan bitta `OWNER_ADMIN`** (`docs/06` §1).

```sql
owner_admin_key BIGINT UNSIGNED
  GENERATED ALWAYS AS (
    CASE WHEN role = 'OWNER_ADMIN' THEN restaurant_id END
  ) STORED,
UNIQUE KEY users_owner_admin_key_unique (owner_admin_key)
```

Ikkinchi `OWNER_ADMIN` qo'shishga urinish DB darajasida rad etiladi —
kod xatosi ham bu qoidani buza olmaydi.

### Nega app darajasida ham lock kerak

Generated column **ikkinchi yozuvni rad etadi**, lekin foydalanuvchiga
xato ko'rsatadi. Yaxshi UX uchun `SessionService` `tables` qatorini
`lockForUpdate()` bilan oladi va ikkinchi so'rovga **mavjud sessionni**
qaytaradi. Generated column — oxirgi himoya chizig'i.
