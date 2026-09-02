# 01 — ARXITEKTURA

---

## 1. UMUMIY SXEMA

```
                     SMART RESTAURANT
                           │
                    ┌──────┴──────┐
                    │   BACKEND   │
                    │  API + WS   │
                    └──────┬──────┘
       ┌───────────────────┼───────────────────┐
       ▼                   ▼                   ▼
 CUSTOMER PWA         ADMIN PANEL        WAITER PWA
    (NFC)              (Desktop)           (Phone)
```

Business flow:
```
NFC → Table aniqlanadi → Guest count → Menyu → Cart → Order
→ Admin ACCEPT → Auto assign free waiter → Waiter ACCEPT
→ (waiter jismonan oshxonaga boradi) → Waiter "YETKAZILDI"
→ Customer yana order bera oladi → ... → Kassa → Admin "TO'LANDI"
→ Session CLOSED → Table AVAILABLE
```

---

## 2. ENTITY'LAR

```
Restaurant
   ├── Tables
   │      └── TableSessions
   │              ├── Orders → OrderItems
   │              ├── Payments
   │              └── WaiterCalls
   ├── Categories → Products
   ├── Users (Admin / Waiter)
   ├── Notifications
   └── Expenses
```

**MUHIM:** `Table ≠ TableSession`. Stol doimiy, session vaqtinchalik.

---

## 3. STATUS'LAR

### Table status
`AVAILABLE` · `ACTIVE` · `ORDER_PENDING` · `WAITER_ASSIGNED` · `DELIVERED` · `WAITING_PAYMENT`

### Session status
`ACTIVE` → `WAITING_PAYMENT` → `PAID` → `CLOSED`

### Order status va ruxsat etilgan transitionlar
```
PENDING            → ACCEPTED | CANCELLED
ACCEPTED           → ASSIGNED | WAITING_FOR_WAITER | CANCELLED
WAITING_FOR_WAITER → ASSIGNED
ASSIGNED           → WAITER_ACCEPTED
WAITER_ACCEPTED    → DELIVERING
DELIVERING         → DELIVERED
DELIVERED          → (final)
CANCELLED          → (final)
```
Boshqa har qanday transition → `422 INVALID_STATUS_TRANSITION`.

### Waiter status
`FREE` · `BUSY` · `OFFLINE`

### Payment
status: `PENDING` · `PAID` · `REFUNDED`
method: `CASH` · `CARD` · `OTHER` (kelajakda PAYME / CLICK / UZUM)

### Waiter call
`PENDING` · `ASSIGNED` · `ACCEPTED` · `COMPLETED` · `CANCELLED`

---

## 4. NFC

Har stolga alohida tag:
```
TABLE 1 → https://restaurant.uz/t/{nfc_token}
TABLE 2 → https://restaurant.uz/t/{nfc_token}
```

Qoidalar:
- NFC ichida **maxfiy ma'lumot saqlanmaydi**.
- URL'da `table_id` emas, **`nfc_token`** (random, predictable emas).
- Backend `nfc_token → table → restaurant_id` bog'lanishini tekshiradi.
- Bir restoran boshqa restoran table'idan foydalana olmaydi.

---

## 5. DATABASE SCHEMA

### restaurants
`id, name, slug, phone, address, currency, default_locale, timezone, is_active, created_at, updated_at`

### users
`id, restaurant_id, name, phone, username, email, password, role, pin, status, locale, last_free_at, is_active, created_at, updated_at`
> `role`: ADMIN | WAITER (kelajakda OWNER, MANAGER, CASHIER)
> `status`: FREE | BUSY | OFFLINE (waiter uchun)
> `last_free_at` — assignment algoritmi uchun

### tables
`id, restaurant_id, number, name, capacity, nfc_uid, nfc_token, status, is_active, created_at, updated_at`
> unique: `(restaurant_id, number)`, unique: `nfc_token`

### table_sessions
`id, restaurant_id, table_id, guest_count, status, customer_token, opened_at, closed_at, total_amount, paid_amount, created_at, updated_at`
> index: `(table_id, status)`

### categories
`id, restaurant_id, name_ru, name_uz, slug, image, sort_order, is_active, created_at, updated_at`

### products
`id, restaurant_id, category_id, name_ru, name_uz, description_ru, description_uz, image, price, weight, preparation_time, discount, is_available, is_active, sort_order, created_at, updated_at`

### orders
`id, restaurant_id, table_id, session_id, waiter_id, client_order_uuid, order_number, status, guest_count, subtotal, discount, total, accepted_at, assigned_at, waiter_accepted_at, delivered_at, cancelled_at, created_at, updated_at`
> unique: `client_order_uuid` (idempotency)
> `session_id` nullable — draft order uchun

### order_items
`id, order_id, product_id, product_name_ru_snapshot, product_name_uz_snapshot, price_snapshot, quantity, subtotal, created_at, updated_at`

### payments
`id, restaurant_id, session_id, amount, method, status, paid_at, received_by, transaction_reference, created_at, updated_at`

### waiter_calls
`id, restaurant_id, table_id, session_id, created_by, assigned_waiter_id, status, message, created_at, accepted_at, completed_at`

### notifications
`id, restaurant_id, user_id, type, title_ru, title_uz, message_ru, message_uz, entity_type, entity_id, is_read, voice_played, created_at`
> type: NEW_ORDER | WAITER_CALL | ORDER_ACCEPTED | ORDER_ASSIGNED | ORDER_DELIVERED | PAYMENT_RECEIVED

### expenses
`id, restaurant_id, title, amount, category, description, expense_date, created_by, created_at, updated_at`

### activity_logs
`id, restaurant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, created_at`

---

## 6. SERVICE QATLAMI

| Service | Vazifasi |
|---|---|
| `SessionService` | session ochish/topish/yopish, unpaid tekshirish |
| `OrderService` | order yaratish, narx qayta hisoblash, status transition |
| `WaiterAssignmentService` | avtomatik afitsant biriktirish |
| `PaymentService` | to'lov qabul qilish, session yopish, draft order chiqarish |
| `NotificationService` | notification yaratish + broadcast |
| `ReportService` | kunlik/haftalik/oylik hisobotlar |

---

## 7. WAITER ASSIGNMENT ALGORITMI

```
1. status = FREE bo'lgan waiterlarni ol (shu restaurant_id bo'yicha)
2. Eng kam active orderga ega bo'lganini tanla
3. Teng bo'lsa — last_free_at eng eski bo'lganini tanla
4. Assign qil, waiter → BUSY, order → ASSIGNED
5. FREE waiter yo'q bo'lsa → order = WAITING_FOR_WAITER (navbat)
6. Waiter DELIVERED bosganda → FREE → navbatdagi eng eski order avtomatik assign
```

---

## 8. ORDER SUBMIT — TRANSACTION

```
BEGIN TRANSACTION
  1. nfc_token / table tekshir
  2. client_order_uuid mavjudmi? → mavjud order qaytar (idempotent)
  3. Table'ning active session'i bormi?
  4. Oldingi session WAITING_PAYMENT emasmi?
       ha → order draft sifatida saqla (session_id = null), 409 qaytar
  5. Yetkazilmagan order bormi? → ha → 409 ORDER_NOT_DELIVERED
  6. Mahsulotlar is_available = true mi?
  7. Narxlarni DB'dan qayta hisobla (frontend narxi e'tiborga olinmaydi)
  8. order + order_items yarat
  9. session.total_amount yangilanadi
COMMIT
→ broadcast OrderCreated
ROLLBACK on error
```

---

## 9. API — `/api/v1/`

### Customer (guest, `customer_session_token` bilan)
```
GET   /t/{nfc_token}                    → table + session holati
POST  /sessions                         → session ochish (guest_count)
GET   /menu                             → kategoriyalar + mahsulotlar
GET   /sessions/{token}                 → session + orderlar
POST  /orders                           → order yuborish (idempotent)
GET   /orders/{id}                      → order status
POST  /waiter-calls                     → afitsant chaqirish
```

### Waiter (Sanctum)
```
GET   /waiter/orders                    → menga assign qilinganlar
POST  /waiter/orders/{id}/accept
POST  /waiter/orders/{id}/deliver
GET   /waiter/calls
POST  /waiter/calls/{id}/accept
POST  /waiter/calls/{id}/complete
POST  /waiter/status                    → FREE / OFFLINE
GET   /waiter/profile
```

### Admin (Sanctum)
```
GET   /admin/dashboard
GET   /admin/orders
POST  /admin/orders/{id}/accept
POST  /admin/orders/{id}/cancel
GET   /admin/tables            CRUD
GET   /admin/products          CRUD
GET   /admin/categories        CRUD
GET   /admin/waiters           CRUD
POST  /admin/payments                   → to'lov qabul qilish
POST  /admin/sessions/{id}/close        → majburiy yopish (audit log)
GET   /admin/reports?from=&to=
GET   /admin/expenses          CRUD
GET   /admin/notifications
```

Barcha javob: `{ success, data, message_ru, message_uz, error_code }`

---

## 10. REAL-TIME (WebSocket)

Kanal: `private-restaurant.{restaurant_id}`, `private-waiter.{user_id}`, `public-table.{nfc_token}`

Eventlar:
```
OrderCreated · OrderAccepted · OrderAssigned · OrderAcceptedByWaiter
OrderDelivering · OrderDelivered
WaiterCallCreated · WaiterCallAccepted · WaiterCallCompleted
PaymentCompleted · TableSessionCreated · TableSessionClosed
```

---

## 11. OVOZLI BILDIRISHNOMA (Admin)

Web Speech API. Til admin tanloviga qarab (ru/uz):

| Holat | UZ | RU |
|---|---|---|
| Yangi order, stol 2 | "Ikkinchi stoldan yangi buyurtma bor" | "Новый заказ со второго стола" |
| Chaqiruv, stol 5 | "Beshinchi stoldan chaqruv bor" | "Вызов с пятого стола" |

Qoida: bir `event_id` = bir marta ovoz. Sahifa refresh bo'lsa qayta aytilmasin (`voice_played = true`).

---

## 12. EDGE CASE — MIJOZ TO'LAMAY KETDI

```
Session #501 → WAITING_PAYMENT, TOTAL 310 000
Yangi mijoz NFC skanerlaydi
  → Menyu OCHILADI
  → Cart tayyorlash MUMKIN
  → "Buyurtma berish" bosilganda → BLOCK
  → Order draft sifatida saqlanadi (session_id = null)
  → Xabar: "Turib ketgan mijoz to'lov qilyapti. Iltimos, bir oz kuting."
Admin "TO'LANDI" bosadi
  → Old session CLOSED
  → Yangi session yaratiladi
  → Draft order avtomatik yangi sessionga biriktiriladi
  → Narxlar DB'dan QAYTA olinadi (cart uzoq turgan bo'lishi mumkin)
  → Order flow boshlanadi
```

---

## 13. XAVFSIZLIK

HTTPS · password hashing · rate limiting · authorization (Policy) · input validation · SQL injection / XSS himoya · file upload MIME + size validation · secure cookie · API throttling · audit log.

Customer hech qachon `price`, `total`, `restaurant_id`, `waiter_id`, `status` qiymatlarini o'zgartira olmasin.

Rasm DB'da binary saqlanmaydi — faqat `image_url`.

---

## 14. HISOBOTLAR

```
Revenue        = SUM(paid session totals)
Expenses       = SUM(expenses)
Profit         = Revenue − Expenses
Average check  = Revenue / Closed sessions
```

Davrlar: Bugun · Kecha · 7 kun · 30 kun · Custom.
Ko'rsatkichlar: Total Revenue, Orders, Guests, Average Check, Products Sold, Paid/Unpaid, Cancelled, Discounts, Expenses, Net Profit, Top mahsulotlar.
