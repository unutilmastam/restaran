# 06 — SAAS (MULTI-RESTAURANT)

> ⚠️ **BU FAYL TO'LIQ EMAS.**
> Foydalanuvchi 2026-09-02 da `docs/06-SAAS.md` §12 savollariga javob berdi,
> ammo faylning o'zi (§1–§11) repoda hech qachon bo'lmagan — na shu branchda,
> na `main` da, na bir commitda.
>
> Quyida **faqat tasdiqlangan javoblar** aynan yozilgan. §1–§11 (SaaS
> arxitekturasi, status mashinasi, super admin panel qamrovi, to'lov oqimi)
> hali yo'q — ular kelgach shu faylga qo'shiladi.
>
> Bu yerda hech qanday biznes qoida **o'ylab topilmagan** (CLAUDE.md §2.11).

---

## 12. TASDIQLANGAN QARORLAR (2026-09-02)

### 12.1 Tarif narxlari

- Narxlar **kodda yozilmaydi** — `plans` jadvalida saqlanadi.
- SUPER_ADMIN panelidan tahrirlanadi: **nom, kun soni, narx, faol/nofaol**.
- Boshlang'ich seed:

  | Kod | Kun | Narx |
  |---|---|---|
  | `MONTHLY` | 30 | 0 |
  | `QUARTERLY` | 90 | 0 |
  | `YEARLY` | 365 | 0 |

  Narxni keyin super admin kiritadi.
- **Narx o'zgarganda eski `subscription_payments` yozuvlari o'zgarmaydi** —
  to'langan summa **snapshot** sifatida saqlanadi.
  > Bu `order_items.price_snapshot` bilan bir xil printsip (CLAUDE.md §3.3).

### 12.2 Trial

- **7 kun.**
- Yangi restoran yaratilganda avtomatik `TRIAL`, `expires_at = now() + 7 kun`.
- Trial tugagach — **oddiy `EXPIRED` oqimi** (alohida holat emas).

### 12.3 Telegram

- **Yangi bot yaratiladi.** Token `.env` da: `TELEGRAM_BOT_TOKEN`.
- Admin panelda **"Telegramni ulash"** tugmasi → deep link `/start <token>`
  → `chat_id` saqlanadi.
- Bosqich: **PHASE 13.6**.

### 12.4 Bir nechta ADMIN

- Bitta restoranda **bir nechta ADMIN** bo'lishi mumkin.
- Restoran egasi o'z admin panelidan qo'shimcha admin qo'sha oladi.
- **Birinchi admin — `OWNER_ADMIN`:**
  - o'chirib bo'lmaydi,
  - faqat SUPER_ADMIN o'zgartira oladi.
  > Sabab: restoran o'zini o'zi qulflab qo'yishidan himoya.

### 12.5 Restoran o'chirish — 2 xil

**a) ARXIVLASH (soft delete) — default variant**
- `deleted_at` qo'yiladi, ma'lumot **qoladi**.
- SUPER_ADMIN qayta tiklay oladi.

**b) BUTUNLAY O'CHIRISH**
- Barcha bog'liq ma'lumot **va rasmlar** o'chadi.
- Faqat **arxivlangan** restoran uchun mumkin.
- Tasdiqlash: restoran **NOMINI qo'lda yozish** talab qilinadi.
- `activity_logs` ga yoziladi.

### 12.6 Aloqa ma'lumotlari

- `settings` jadvalida: `contact_phone`, `contact_telegram`,
  `contact_note` (**ru + uz**).
- SUPER_ADMIN panelidan o'zgartiriladi.
- To'lov sahifasi shu qiymatlarni ko'rsatadi.

### 12.7 Limitlar

- `max_tables`, `max_products`, `max_waiters` — **restoranga** biriktiriladi,
  tarifga emas.
- SUPER_ADMIN har restoran uchun **alohida** o'zgartiradi.
- Default: **30 / 100 / 10**.
- Uzoq muddatli tarif = **arzonroq narx**, limit farqi yo'q.

### 12.8 Kontent kim kiritadi

- Menyu, mahsulot rasmlari, restoran logotipi va nomi — **restoran egasi**.
- SUPER_ADMIN faqat **restoran yaratadi** va **admin userini beradi**.
- Yangi restoranda **demo kategoriya/mahsulot QO'YILMASIN** — bo'sh menyu
  bilan boshlanadi.
  > ⚠️ Bu `docs/03-PHASES.md` PHASE 2 seederiga tegishli: 5 kategoriya +
  > 25 mahsulot seeder **faqat local/demo** uchun qoladi, yangi restoran
  > yaratish oqimida ishlatilmaydi.

---

## KEYINGI QADAM UCHUN KERAK

§1–§11 kelmaguncha quyidagilar noaniq — ular DB va API strukturasiga
bevosita ta'sir qiladi, shuning uchun **o'ylab topilmaydi**:

1. `restaurants.status` mashinasi: `TRIAL → ACTIVE → EXPIRED → ?`
   Qanday o'tishlar ruxsat etilgan?
2. **EXPIRED restoranda nima bo'ladi?** Customer NFC skanerlasa nima
   ko'radi? Admin panel qanchalik bloklanadi? Waiter PWA ishlaydimi?
3. To'lov **qo'lda** (super admin tasdiqlaydi) deb tushunildi (§12.6
   aloqa ma'lumotlari shuni ko'rsatadi) — avtomatik to'lov tizimi
   (Payme/Click) hozir yo'qmi?
4. `subscription_payments` yozuvini kim yaratadi — restoran egasi
   "to'ladim" deb yuboradimi, yoki super admin qo'lda kiritadimi?
5. Muddat uzaytirilganda: `expires_at` **bugundan** hisoblanadimi yoki
   **eski `expires_at` ustiga** qo'shiladimi?
6. `SUPER_ADMIN` qayerda yashaydi — `users` jadvalida `restaurant_id = null`
   bilanmi, yoki alohida jadvaldami?
7. **PHASE raqamlash.** §12.3 "PHASE 13.6" deydi — demak sizning
   hujjatingizda phase'lar kichik bosqichlarga bo'lingan. Men o'z
   raqamlashimni o'ylab topsam, sizniki bilan to'qnashadi.
