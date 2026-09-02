# Smart Restaurant

NFC asosida ishlaydigan restoran buyurtma boshqaruv tizimi — **SaaS**
(bitta server, ko'p restoran).

To'rt interfeys: **Customer PWA** (NFC) · **Admin Panel** · **Waiter PWA** ·
**Super Admin** (platforma egasi). Ikki til: **RU + UZ**.

> Loyihaning source of truth — [`CLAUDE.md`](CLAUDE.md) va [`docs/`](docs/).
> Joriy holat: [`docs/PROGRESS.md`](docs/PROGRESS.md).

---

## Talablar

| Nima | Versiya | Izoh |
|---|---|---|
| PHP | **8.3** | hosting `ea-php83` bilan bir xil |
| Composer | 2.x | |
| MySQL | **8.0** | `utf8mb4`. SQLite ishlatilmaydi — quyida sabab |
| Node.js | 22 | faqat development/build uchun |

**Redis, Reverb, Docker, supervisor kerak emas.** Hosting cPanel shared —
cache/queue `database` driverida, WebSocket Pusher orqali
(qarang [`docs/05-PHASE0-PLAN.md`](docs/05-PHASE0-PLAN.md) §0).

---

## O'rnatish

### 1. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

`.env` da MySQL ma'lumotlarini to'ldiring, so'ng bazani yarating:

```sql
CREATE DATABASE smart_restaurant      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE smart_restaurant_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate
php artisan storage:link
php artisan serve            # http://localhost:8000
```

Tekshirish:

```bash
curl -H "Accept-Language: uz" http://localhost:8000/api/v1/health
```

### 2. Frontend

```bash
cd frontend
npm install                  # npm workspaces — bitta o'rnatish 3 ta app uchun

npm run dev:customer         # http://localhost:5173/t/{nfc_token}
npm run dev:admin            # http://localhost:5174
npm run dev:waiter           # http://localhost:5175
npm run dev:super            # http://localhost:5176
```

Har bir app uchun `.env` ni `.env.example` dan nusxalang.

---

## Struktura

```
backend/            Laravel 11 — faqat API, Blade UI yo'q
  app/Enums/        status matritsalari (docs/01 §3)
  app/Services/     business logic (Controller ichida EMAS)
  app/Support/      ApiResponse — javob konverti
  lang/{ru,uz}/     xato va validatsiya matnlari

frontend/           npm workspaces
  shared/           @sr/shared — i18n, api client, types, format
  customer/         PWA (NFC orqali ochiladi)
  admin/            kassa/desktop
  waiter/           afitsant telefoni
  super/            SUPER_ADMIN — restoranlar, obuna, tariflar

deploy/             cPanel yo'riqnomasi (PHASE 16)
docs/               arxitektura, i18n, phase'lar, test scenario, SaaS
```

---

## Bundle byudjeti

Customer PWA NFC orqali **mobil internetda** ochiladi, shuning uchun
hajm qattiq nazoratda: **≤ 100 KB gzip**.

| Hozir | |
|---|---|
| JS | 55.6 KB gz |
| CSS | 3.2 KB gz |
| Cart (lazy chunk) | 1.1 KB gz |

Shu sababli customer appda **`react-router` ishlatilmaydi** (bitta NFC
kirish nuqtasi), `i18next` va `axios` o'rniga `shared/` dagi kichik
implementatsiyalar turadi. Yangi bog'liqlik qo'shishdan oldin
`npm run build` bilan hajmni tekshiring.

## Testlar

```bash
cd backend  && php artisan test     # PHPUnit — MySQL bazasida
cd frontend && npm test             # Vitest — i18n parity, format
cd frontend && npm run typecheck
```

**Nega SQLite emas?** `table_sessions.active_key` generated column +
`UNIQUE`, `lockForUpdate` va `DECIMAL` xatti-harakati MySQL'ga bog'liq.
Ular aynan concurrency va pul hisobini himoya qiladi, shuning uchun
testlar haqiqiy MySQL'da yuriladi
([`docs/05-PHASE0-PLAN.md`](docs/05-PHASE0-PLAN.md) §4.2).

---

## API

Baza: `/api/v1/`. Har bir javob bitta konvertda (docs/01 §9):

```json
{
  "success": true,
  "data": {},
  "message_ru": null,
  "message_uz": null,
  "error_code": null
}
```

Ikkala til ham qaytadi — frontend qaysi biri kerakligini o'zi tanlaydi,
til almashtirilganda so'rov qayta yuborilmaydi.

To'liq contract: [`docs/05-PHASE0-PLAN.md`](docs/05-PHASE0-PLAN.md) §3.

| Header | Kim |
|---|---|
| `Accept-Language: ru\|uz` | hamma |
| `Authorization: Bearer <token>` | admin / waiter (Sanctum) |
| `X-Customer-Token: <64 belgi>` | customer |

## Domen strategiyasi (docs/06-SAAS.md §7)

```
menu.itcode.uz/r/{slug}/t/{nfc_token}   Customer PWA
admin.itcode.uz                          Admin
afitsant.itcode.uz                       Waiter
super.itcode.uz                          SUPER_ADMIN
api.itcode.uz                            Laravel API
```

---

## Ish tartibi

Phase'lar ketma-ket bajariladi ([`docs/03-PHASES.md`](docs/03-PHASES.md)).
Bir phase tugamaguncha keyingisi boshlanmaydi. Har phase oxirida:
test → migration check → API check → UI check → `docs/PROGRESS.md`.
