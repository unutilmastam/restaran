# DEPLOY — cPanel shared hosting

> **Bu papka PHASE 16 da to'ldiriladi.** Hozir faqat skelet va tasdiqlangan
> hosting parametrlari saqlanadi, shunda repo strukturasi keyin qayta
> qurilmaydi.

## Tasdiqlangan hosting (2026-09-02)

| Parametr | Qiymat |
|---|---|
| PHP | 8.3 (`ea-php83`) — Laravel 11 mos |
| MySQL | 8.0.44 Community Server |
| Charset | `utf8mb4` |
| Redis | ❌ yo'q → `database` cache/queue |
| Reverb | ❌ yo'q → Pusher Channels |
| Supervisor | ❌ yo'q → cPanel cron |
| Disk | 1 GB |
| PHP-FPM | hozircha o'chirilgan (keyin yoqiladi) |

MySQL 8.0.44 — `table_sessions.active_key` generated column + `UNIQUE`
yechimi (docs/05-PHASE0-PLAN.md §2.3) shu versiyada to'liq ishlaydi.

## Rejalashtirilgan joylashuv

```
~/apps/backend/          ← Laravel (public_html DAN TASHQARIDA)
~/public_html/           ← customer dist
api.domain.uz            ← subdomain, docroot = ~/apps/backend/public
admin.domain.uz          ← admin dist
waiter.domain.uz         ← waiter dist
```

## cPanel cron (PHASE 16 da qo'shiladi)

```cron
* * * * * cd ~/apps/backend && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/apps/backend && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

## Qat'iy qoidalar

- `node_modules` va `vendor/` (dev) serverga **yuklanmaydi**.
- Frontend build **lokalda yoki CI da** qilinadi, serverga faqat `dist/` chiqadi.
- `composer install --no-dev --optimize-autoloader`.
- `APP_DEBUG=false`, `APP_ENV=production`.
