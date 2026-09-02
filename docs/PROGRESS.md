# PROGRESS

| Phase | Nomi | Holat | Izoh |
|---|---|---|---|
| 0 | Tayyorgarlik | 🟡 Reja tayyor, tasdiq kutilmoqda | `docs/05-PHASE0-PLAN.md`. 12 ta savol javob kutmoqda (§5) |
| 1 | Project setup | ⬜ | |
| 2 | Database | ⬜ | |
| 3 | Customer PWA | ⬜ | |
| 4 | Session tizimi | ⬜ | |
| 5 | Order tizimi | ⬜ | |
| 6 | Admin panel | ⬜ | |
| 7 | Waiter PWA | ⬜ | |
| 8 | Auto assignment | ⬜ | |
| 9 | Real-time (Pusher) | ⬜ | |
| 10 | Ovozli bildirishnoma | ⬜ | |
| 11 | Afitsant chaqiruvi | ⬜ | |
| 12 | To'lov / session yopish | ⬜ | |
| 13 | Hisobotlar | ⬜ | |
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
