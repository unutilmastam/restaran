# 04 — KRITIK BIZNES TEST SCENARIO

Bu tizimning **qabul qilish mezoni**. Barcha 30 qadam yashil bo'lmasa, loyiha tugagan hisoblanmaydi.

Fayl: `backend/tests/Feature/CriticalBusinessFlowTest.php`

---

## SCENARIO

| № | Qadam | Kutilgan natija |
|---|---|---|
| 1 | Table #2 yaratilsin | `nfc_token` bilan, status `AVAILABLE` |
| 2 | Customer A NFC orqali Table #2 ga kirsin | Stol aniqlandi, session yo'q |
| 3 | 3 guests tanlasin | Session #1 `ACTIVE`, `guest_count=3`, `customer_token` berildi |
| 4 | Order #105 yaratsin | status `PENDING`, total backendda hisoblangan |
| 5 | Admin orderni qabul qilsin | `ACCEPTED` |
| 6 | Hasan waiterga assign qilinsin | `ASSIGNED`, Hasan `BUSY` |
| 7 | Hasan orderni qabul qilsin | `WAITER_ACCEPTED` |
| 8 | Hasan ovqatni olib kelsin | `DELIVERING` |
| 9 | Hasan "Yetkazildi" bossin | `DELIVERED`, Hasan `FREE` |
| 10 | Customer yana Order #106 yaratsin | Yangi order, **eski session ichida** |
| 11 | #106 boshqa waiterga assign qilinsin | Akmal yoki Ali |
| 12 | Customer ketsin, to'lov qilinmasin | Payment `PENDING` |
| 13 | Session `WAITING_PAYMENT` bo'lsin | Table `WAITING_PAYMENT` |
| 14 | Customer B NFC orqali Table #2 ga kirsin | Kirish ruxsat etiladi |
| 15 | Menyu ochilsin | 200, mahsulotlar ko'rinadi |
| 16 | Customer B cartga mahsulot qo'shsin | Cart ishlaydi |
| 17 | "Buyurtma berish" bosilsin | So'rov yuboriladi |
| 18 | Backend orderni bloklasin | 409 `SESSION_WAITING_PAYMENT`, order draft (`session_id = null`) |
| 19 | Customer xabar ko'rsin | UZ: "Turib ketgan mijoz to'lov qilyapti..." / RU: "Предыдущий гость оплачивает счёт..." |
| 20 | Admin old session uchun "To'landi" bossin | Payment `PAID` |
| 21 | Old session CLOSED bo'lsin | `closed_at` to'ldirilgan |
| 22 | Yangi session yaratilsin | Session #2 `ACTIVE` |
| 23 | Customer B orderi yangi sessionga o'tsin | `session_id = 2`, narxlar DB'dan qayta hisoblangan |
| 24 | Order Admin'da ko'rinsin | `PENDING`, broadcast yuborilgan |
| 25 | Free waiterga assign qilinsin | `ASSIGNED` |
| 26 | Waiter qabul qilsin | `WAITER_ACCEPTED` |
| 27 | Yetkazsin | `DELIVERED` |
| 28 | Session oxirida payment qilinsin | Payment `PAID` |
| 29 | Session CLOSED | ✔ |
| 30 | Table AVAILABLE | ✔ |

---

## QO'SHIMCHA MAJBURIY TESTLAR

### Idempotency
Bir xil `client_order_uuid` bilan 2 marta POST → DB'da **1 ta order**.

### Narx manipulyatsiyasi
Frontend `price: 1` yuboradi → backend DB narxini ishlatadi, total to'g'ri.

### Order lock
Yetkazilmagan order bor → yangi order → 409 `ORDER_NOT_DELIVERED`.

### Mahsulot mavjud emas
`is_available = false` mahsulot cartda → 422 `PRODUCT_UNAVAILABLE`.

### Noto'g'ri status transition
`PENDING → DELIVERED` → 422 `INVALID_STATUS_TRANSITION`.

### Waiter izolyatsiyasi
Waiter A, Waiter B ning orderini `accept`/`deliver` qila olmaydi → 403.

### Multi-tenant izolyatsiyasi
Restoran A admini Restoran B stollari/orderlarini ko'ra olmaydi → 403/404.

### Concurrency
Bir stolga bir vaqtda 2 ta session ochish so'rovi → **1 ta session** yaratiladi.

### Waiter navbati
Barcha waiterlar BUSY → order `WAITING_FOR_WAITER` → biri FREE bo'lgach avtomatik assign.

### i18n
`ru.json` va `uz.json` kalitlari to'liq mos. API `Accept-Language` ga to'g'ri javob beradi.

### Ovoz takrorlanmasligi
Bir notification uchun `voice_played` bir marta `true` bo'ladi; refreshdan keyin qayta o'qilmaydi.
