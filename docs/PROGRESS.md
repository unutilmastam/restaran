# PROGRESS

| Phase | Nomi | Holat | Izoh |
|---|---|---|---|
| 0 | Tayyorgarlik | ✅ Tugadi | `docs/05-PHASE0-PLAN.md`. 12 ta savol yopildi (§5) |
| 1 | Project setup | ✅ Tugadi | Laravel 11.56 + Sanctum + Pusher · **4 ta** Vite app + `@sr/shared` · i18n ru/uz · `/api/v1/health` · SaaS config/i18n/types · 11 backend + 8 frontend test yashil |
| 2 | Database | ✅ Tugadi | 23 migration fayli → 29 jadval · 11 enum · 20 model · global scope · seeder |
| 2.5 | SaaS schema | ✅ Tugadi | `plans`, `subscriptions`, `subscription_payments` + `restaurants`/`users`/`settings` · snapshot kafolati |
| 3 | Customer PWA | ⬜ Keyingi | |
| 4 | Session tizimi | ⬜ | |
| 5 | Order tizimi | ⬜ | |
| 6 | Admin panel | ⬜ | |
| 6.5 | Obuna nazorati | ⬜ | `CheckSubscription`, grace period 3 kun |
| 7 | Waiter PWA | ⬜ | |
| 8 | Auto assignment | ⬜ | |
| 9 | Real-time (Pusher) | ⬜ | |
| 10 | Ovozli bildirishnoma | ⬜ | |
| 11 | Afitsant chaqiruvi | ⬜ | |
| 12 | To'lov / session yopish | ⬜ | |
| 13 | Hisobotlar | ⬜ | |
| 13.5 | Super admin paneli | ⬜ | `frontend/super/`, restoranlar, obuna, arxiv/o'chirish |
| 13.6 | Xabarnomalar / Telegram | ⬜ | yangi bot, 5 kunlik ogohlantirish |
| 14 | Xavfsizlik | ⬜ | |
| 15 | Testing | ⬜ | |
| 16 | Production (cPanel) | ⬜ | |

---

## Muhim qarorlar tarixi

| Sana | Qaror | Sabab |
|---|---|---|
| 2026-09-02 | Reverb → **Pusher** | cPanel shared hostingda doimiy PHP process yo'q |
| 2026-09-02 | Redis → **`database`** cache/queue | Redis yo'q |
| 2026-09-02 | Queue worker → **cPanel cron** | Supervisor yo'q |
| 2026-09-02 | Disk byudjeti **1 GB** | Rasm webp+resize, log retention, `node_modules` serverga chiqmaydi |
| 2026-09-02 | Hosting tasdiqlandi: **PHP 8.3, MySQL 8.0.44, utf8mb4** | `active_key` generated column yechimi ishlaydi |
| 2026-09-02 | Customer real-time = **polling**, WebSocket emas | Pusher free = 100 connection; `nfc_token` doimiy kanal xavfsiz emas |
| 2026-09-02 | Frontend **subdomain** sxemasi | `VITE_BASE` env orqali subdirectory'ga o'tish ochiq qoldi |
| 2026-09-02 | Test framework **PHPUnit 11**, Pest emas | Pest composer plugin talab qiladi — CI/agent muhitida ishonchsiz |
| 2026-09-02 | **Tailwind v3**, v4 emas | v4 Chrome 111+/Safari 16.4+ talab qiladi; customer PWA eski telefonlarda ham ochilishi kerak |
| 2026-09-02 | **React 18** `overrides` bilan qulflandi | npm avtomatik React 19 tortdi, CLAUDE.md §4 esa 18 ni belgilaydi |
| 2026-09-02 | Backendda `package.json` yo'q | Blade UI va asset build yo'q — serverda `node_modules` bo'lmaydi |
| 2026-09-02 | **SaaS qatlami qo'shildi** (`docs/06-SAAS.md`) | Bitta server, ko'p restoran + qo'lda tasdiqlanadigan obuna |
| 2026-09-02 | 4-chi frontend app: `super` | `docs/06-SAAS.md` §7 — `super.itcode.uz` alohida panel |
| 2026-09-02 | `OWNER_ADMIN` roli | Restoran o'zini o'zi qulflab qo'ymasligi uchun (javob 4) |
| 2026-09-02 | Yangi restoranda **demo kontent yo'q** | Javob 8 — hujjat §9 dagi "demo kategoriya/mahsulot" olib tashlandi |
| 2026-09-02 | `settings.restaurant_id` **nullable** | `null` = platforma darajasi (aloqa ma'lumotlari, javob 6) |
| 2026-09-02 | `activity_logs.restaurant_id` ham **nullable** | SUPER_ADMIN platforma amallari + restoran o'chganda audit qolishi kerak (`ON DELETE SET NULL`) |
| 2026-09-02 | Global scope **rolga qarab emas, route'ga qarab** chetlab o'tiladi | SUPER_ADMIN `/admin/*` da ham faqat o'z kontekstini ko'radi |
| 2026-09-02 | Scope restoran aniqlanmasa **`1 = 0`** qaytaradi | Kod xatosida ma'lumot sizib chiqmasin — sahifa bo'sh qolsin |
| 2026-09-02 | `App\Casts\Money` — API'da **son**, string emas | Laravel `decimal:2` string qaytaradi; pul PHP'da yig'ilmaydi |
| 2026-09-02 | `SubscriptionPayment` **o'zgarmas** (update/delete bloklangan) | `plans.price` o'zgarsa moliyaviy tarix buzilmasin |
