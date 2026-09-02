# 02 — IKKI TILLILIK (RU / UZ)

Tizim **rus** va **o'zbek** tillarida ishlaydi. Bu keyin qo'shiladigan narsa emas — **1-phase'dan boshlab** quriladi.

---

## 1. ASOSIY QOIDA

❌ Kodda hardcode qilingan matn yozilmasin.
✅ Har bir matn kalit orqali chaqiriladi: `t('order.delivered')`

---

## 2. TIL QAYERDA SAQLANADI

| Kim | Qayerda |
|---|---|
| Customer | `localStorage.locale` + URL `?lang=ru` |
| Waiter | `users.locale` (DB) |
| Admin | `users.locale` (DB) |
| Restoran default | `restaurants.default_locale` |

Aniqlash tartibi: URL param → localStorage → user.locale → restaurant.default_locale → `uz`.

API so'rovlarida header: `Accept-Language: ru` yoki `uz`.

---

## 3. KONTENT TARJIMASI (DB)

Kategoriya va mahsulot nomlari **ikki ustunda** saqlanadi (JSON emas — oddiy ustun, qidiruv oson):

```
categories: name_ru, name_uz
products:   name_ru, name_uz, description_ru, description_uz
```

Admin panelda mahsulot qo'shishda **ikkala til maydoni ham majburiy**:
```
Nomi (UZ):  [ Osh                    ]
Название (RU): [ Плов                ]
Tavsif (UZ): [ Mol go'shti, guruch   ]
Описание (RU): [ Говядина, рис       ]
```

`order_items` snapshot ikkala tilni ham saqlaydi:
`product_name_ru_snapshot`, `product_name_uz_snapshot`

---

## 4. INTERFEYS TARJIMASI

### Frontend
`frontend/shared/i18n/`
```
shared/i18n/
├── index.ts
├── ru.json
└── uz.json
```
Kutubxona: `react-i18next`.

### Backend
`backend/lang/ru/`, `backend/lang/uz/` — validation, xato xabarlari, notification matnlari.

API javobida ikkala til ham qaytadi (frontend qaysi biri kerakligini oladi):
```json
{
  "success": false,
  "error_code": "SESSION_WAITING_PAYMENT",
  "message_uz": "Turib ketgan mijoz to'lov qilyapti. Iltimos, bir oz kuting.",
  "message_ru": "Предыдущий гость оплачивает счёт. Пожалуйста, подождите."
}
```

---

## 5. TIL ALMASHTIRISH UI

Customer PWA — yuqori o'ng burchakda:
```
[ 🇺🇿 UZ ]  [ 🇷🇺 RU ]
```
Admin/Waiter — profil sozlamalarida.

---

## 6. XATO XABARLARI LUG'ATI (majburiy)

| error_code | UZ | RU |
|---|---|---|
| `PRODUCT_UNAVAILABLE` | Mahsulot hozir mavjud emas | Товар сейчас недоступен |
| `SESSION_NOT_FOUND` | Bu stol uchun faol sessiya topilmadi | Активная сессия для стола не найдена |
| `ORDER_NOT_DELIVERED` | Avvalgi buyurtmangiz hali yetkazilmagan | Ваш предыдущий заказ ещё не доставлен |
| `SESSION_WAITING_PAYMENT` | Turib ketgan mijoz to'lov qilyapti. Iltimos, bir oz kuting. | Предыдущий гость оплачивает счёт. Пожалуйста, подождите. |
| `NO_FREE_WAITER` | Hozircha bo'sh afitsant mavjud emas | Свободных официантов пока нет |
| `NETWORK_ERROR` | Internet bilan aloqa uzildi | Соединение с интернетом потеряно |
| `ORDER_DUPLICATE` | Buyurtma allaqachon yuborilgan | Заказ уже отправлен |
| `INVALID_TABLE` | Stol topilmadi | Стол не найден |
| `INVALID_STATUS_TRANSITION` | Bu amalni bajarib bo'lmaydi | Это действие невозможно |
| `PRICE_CHANGED` | Narxlar yangilandi, savatni tekshiring | Цены обновились, проверьте корзину |

---

## 7. ASOSIY UI KALITLARI

| Kalit | UZ | RU |
|---|---|---|
| `common.table` | Stol | Стол |
| `common.total` | Jami | Итого |
| `common.cancel` | Bekor qilish | Отмена |
| `customer.guest_count` | Necha kishi? | Сколько гостей? |
| `customer.view_menu` | Menyuni ko'rish | Посмотреть меню |
| `customer.cart` | Savat | Корзина |
| `customer.place_order` | Buyurtma berish | Оформить заказ |
| `customer.call_waiter` | Afitsantni chaqirish | Позвать официанта |
| `customer.my_order` | Buyurtmam | Мой заказ |
| `order.pending` | Yuborildi | Отправлен |
| `order.accepted` | Qabul qilindi | Принят |
| `order.assigned` | Afitsantga biriktirildi | Назначен официанту |
| `order.delivering` | Yetkazilmoqda | Доставляется |
| `order.delivered` | Yetkazildi | Доставлен |
| `waiter.new_order` | Yangi buyurtma | Новый заказ |
| `waiter.accept` | Qabul qilish | Принять |
| `waiter.delivered_btn` | Yetkazildi | Доставлено |
| `waiter.status_free` | Bo'sh | Свободен |
| `waiter.status_busy` | Band | Занят |
| `admin.dashboard` | Boshqaruv paneli | Панель управления |
| `admin.orders` | Buyurtmalar | Заказы |
| `admin.tables` | Stollar | Столы |
| `admin.menu` | Menyu | Меню |
| `admin.waiters` | Afitsantlar | Официанты |
| `admin.payments` | To'lovlar | Платежи |
| `admin.reports` | Hisobotlar | Отчёты |
| `admin.expenses` | Xarajatlar | Расходы |
| `admin.settings` | Sozlamalar | Настройки |
| `admin.paid_btn` | To'landi | Оплачено |
| `admin.revenue` | Daromad | Выручка |
| `admin.avg_check` | O'rtacha chek | Средний чек |

---

## 8. OVOZLI BILDIRISHNOMA — SON TARJIMASI

Stol raqami tartib son bilan aytiladi. Ikkala tilda 1–50 gacha lug'at tayyorlansin.

| № | UZ | RU |
|---|---|---|
| 1 | birinchi | первого |
| 2 | ikkinchi | второго |
| 3 | uchinchi | третьего |
| 5 | beshinchi | пятого |
| 10 | o'ninchi | десятого |

Shablon:
- UZ: `"{ordinal} stoldan yangi buyurtma bor"`
- RU: `"Новый заказ со {ordinal} стола"`

Web Speech API `lang`: `ru-RU` yoki `uz-UZ` (uz mavjud bo'lmasa `ru-RU` fallback + matnni lotin o'zbekcha o'qish).

---

## 9. FORMATLASH

- Pul: `190 000 so'm` / `190 000 сум` (bo'sh joy ajratgich)
- Sana: `02.09.2026`
- Vaqt: 24 soatlik `14:25`

---

## 10. TEST

- Har bir tilda barcha kalitlar mavjudligini tekshiradigan test bo'lsin (`ru.json` va `uz.json` kalitlari 1:1 mos).
- Backend: `Accept-Language` header'ga qarab to'g'ri til qaytishini tekshiruvchi feature test.
