# CLAUDE.md — Smart Restaurant

Bu fayl loyihaning **source of truth**i. Har bir sessiyada birinchi bo'lib shu fayl o'qiladi.

---

## 1. LOYIHA

NFC asosida ishlaydigan restoran buyurtma boshqaruv tizimi.

3 ta interfeys:
- **Customer PWA** — NFC orqali ochiladi, ilova o'rnatilmaydi
- **Admin Panel** — kassa/desktop, real-time + ovozli bildirishnoma
- **Waiter PWA** — afitsant telefoni

**Tillar: RU + UZ (ikkalasi ham majburiy).**

---

## 2. QAT'IY TAQIQLAR (buzilmasin)

1. ❌ **Kitchen / Oshpaz paneli YARATILMASIN.** Oshpaz tizimdan foydalanmaydi.
2. ❌ Customer uchun **native mobil ilova** yaratilmasin — faqat browser/PWA.
3. ❌ `Table` va `TableSession` **bitta entity qilinmasin**. Ular alohida jadval.
4. ❌ Yetkazilmagan order bo'lsa — **yangi order qabul qilinmasin**.
5. ❌ `WAITING_PAYMENT` holatidagi stolda yangi order **darhol qabul qilinmasin** (draft sifatida saqlanadi).
6. ❌ Mahsulot narxi **frontenddan olinmasin**. Har doim DB'dan qayta hisoblansin.
7. ❌ Order total **frontendda hisoblanmasin**.
8. ❌ Noto'g'ri status transition'ga ruxsat berilmasin.
9. ❌ Hardcode qilingan matn (string) yozilmasin — hammasi i18n fayllarida.
10. ❌ Mavjud kod o'chirib tashlanmasin — moslashtirilsin.
11. ❌ Noaniq joyda **o'zicha yangi business rule o'ylab topilmasin** — savol berilsin.

---

## 3. MAJBURIY QOIDALAR

1. ✅ Idempotency: har bir order submit'da `client_order_uuid`.
2. ✅ Order submit — **transaction** ichida.
3. ✅ `order_items` da `product_name_snapshot` + `price_snapshot` saqlansin (ru va uz nomi bilan).
4. ✅ `restaurant_id` deyarli barcha jadvallarda (multi-restaurant ready).
5. ✅ Admin va Waiter real-time (WebSocket), refresh shart emas.
6. ✅ Bir event = bir marta ovozli signal (`voice_played` flag).
7. ✅ Business logic Service klasslarda, Controller ichida emas.
8. ✅ Har bir phase oxirida test yozilsin.

---

## 4. TEXNOLOGIYA

| Qatlam | Tanlov |
|---|---|
| Backend | Laravel 11 |
| Database | MySQL 8 (yoki PostgreSQL) |
| Real-time | Laravel Reverb (WebSocket) |
| Cache / Queue | Redis |
| Auth | Laravel Sanctum |
| API | REST, `/api/v1/` |
| Frontend | React 18 + Vite + TypeScript |
| State | Zustand |
| Style | Tailwind CSS |
| i18n | `react-i18next` (frontend) + Laravel `lang/` (backend) |
| Storage | local `storage/products/` → keyin S3 |

Frontend 3 ta alohida app:
```
frontend/customer/   → PWA
frontend/admin/      → responsive web app
frontend/waiter/     → PWA + push
```

---

## 5. REPOSITORY STRUKTURASI

```
smart-restaurant/
├── CLAUDE.md
├── docs/
│   ├── 01-ARCHITECTURE.md
│   ├── 02-I18N-RU-UZ.md
│   ├── 03-PHASES.md
│   └── 04-TEST-SCENARIO.md
├── backend/            # Laravel
│   ├── app/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Events/
│   │   ├── Listeners/
│   │   ├── Policies/
│   │   ├── Http/Requests/
│   │   └── Http/Controllers/Api/V1/
│   ├── database/migrations/
│   ├── lang/{ru,uz}/
│   └── tests/{Unit,Feature}/
└── frontend/
    ├── customer/
    ├── admin/
    ├── waiter/
    └── shared/         # i18n, types, api client
```

---

## 6. ISH TARTIBI

1. Phase'lar **ketma-ket** bajariladi (`docs/03-PHASES.md`).
2. Bir phase tugagach: code review → test → migration check → API check → UI check.
3. Oldingi phase ishlashi tasdiqlanmaguncha keyingisiga o'tilmaydi.
4. Har bir phase oxirida commit: `feat(phase-N): <qisqa tavsif>`.
5. Phase tugagach `docs/PROGRESS.md` yangilanadi.

---

## 7. TESTDAN O'TISHI SHART

`docs/04-TEST-SCENARIO.md` dagi 30 qadamli kritik scenario **to'liq yashil** bo'lishi shart. Bu tizimning qabul qilish mezoni.
