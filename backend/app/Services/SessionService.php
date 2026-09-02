<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Exceptions\BusinessException;
use App\Models\SessionDevice;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Session lifecycle — docs/01-ARCHITECTURE.md §12, docs/03-PHASES.md PHASE 4.
 *
 * `Table` va `TableSession` HECH QACHON birlashtirilmaydi (CLAUDE.md §2.3):
 * stol doimiy, session vaqtinchalik.
 */
class SessionService
{
    public const TOKEN_LENGTH = 64;

    public function __construct(private readonly TableStatusService $tableStatus) {}

    /**
     * Stolning ochiq sessioni (ACTIVE yoki WAITING_PAYMENT).
     *
     * `$locking = true` — QULFLOVCHI o'qish. InnoDB REPEATABLE READ'da
     * oddiy SELECT transaction boshlanishidagi snapshot'ni ko'radi,
     * ya'ni PARALLEL process endi commit qilgan qator KO'RINMAYDI.
     * Qulflovchi o'qish esa har doim eng oxirgi commit qilingan holatni
     * o'qiydi — `openSession()` ning fallback yo'lida shu kerak.
     */
    public function findActiveSession(Table $table, bool $locking = false): ?TableSession
    {
        return TableSession::query()
            ->where('table_id', $table->id)
            ->whereIn('status', $this->occupyingValues())
            ->when($locking, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** To'lanmagan session bormi — yangi buyurtma bloklanadimi (docs/01 §12). */
    public function hasUnpaidSession(Table $table): bool
    {
        return TableSession::query()
            ->where('table_id', $table->id)
            ->where('status', SessionStatus::WAITING_PAYMENT->value)
            ->exists();
    }

    /**
     * Session ochish yoki mavjudiga ulanish.
     *
     * Bir stolga bir vaqtda ikki telefon kirsa ham BITTA session bo'ladi
     * (docs/04-TEST-SCENARIO.md "Concurrency"). Ikki qatlamli himoya:
     *
     *   1. `tables` qatori `lockForUpdate()` bilan olinadi — ikkinchi
     *      so'rov kutadi va mavjud sessionni ko'radi
     *   2. `table_sessions.active_key` generated column + UNIQUE — agar
     *      lock qandaydir sabab bilan ishlamasa DB rad etadi
     *
     * @return array{session: TableSession, token: string, created: bool}
     */
    public function openSession(Table $table, int $guestCount): array
    {
        return DB::transaction(function () use ($table, $guestCount): array {
            // 1-qatlam: stol qatorini qulflaymiz.
            Table::query()->whereKey($table->id)->lockForUpdate()->first();

            $session = $this->findActiveSession($table, locking: true);

            if ($session !== null && $session->status === SessionStatus::ACTIVE) {
                // Mavjud ACTIVE sessionga YANGI QURILMA ulanadi (javob 9):
                // bir stolda bir necha telefon bitta hisobni bo'lishadi.
                // Xato emas — 200 qaytadi.
                return [
                    'session' => $session,
                    'token' => $this->attachDevice($session),
                    'created' => false,
                ];
            }

            if ($session !== null) {
                /*
                 * WAITING_PAYMENT — bu KETAYOTGAN mijozning hisobi.
                 *
                 * ⚠️ Yangi mijoz unga ULANMAYDI. Aks holda u
                 * `/sessions/me` orqali begona buyurtmalar va summani
                 * ko'rardi. O'rniga mijozga DRAFT tokeni beriladi:
                 * u faqat o'z draftini ko'radi (docs/01 §12).
                 */
                throw new BusinessException('SESSION_WAITING_PAYMENT', 409, [
                    'draft_token' => $this->issueDraftToken(),
                ]);
            }

            try {
                $session = TableSession::create([
                    'restaurant_id' => $table->restaurant_id,
                    'table_id' => $table->id,
                    'guest_count' => max(1, $guestCount),
                    'status' => SessionStatus::ACTIVE,
                    'public_id' => $this->publicId(),
                    'opened_at' => now(),
                ]);
            } catch (QueryException $exception) {
                // 2-qatlam: UNIQUE(active_key) ishga tushdi — demak boshqa
                // so'rov bizdan oldin ulgurdi. Uning sessioniga ulanamiz.
                //
                // ⚠️ QULFLOVCHI o'qish shart: oddiy SELECT bu transaction
                // snapshot'ida hali eski holatni ko'radi va NULL qaytaradi
                // (InnoDB REPEATABLE READ). Buni concurrency testi topdi.
                $existing = $this->findActiveSession($table, locking: true);

                if ($existing === null) {
                    throw $exception;
                }

                return [
                    'session' => $existing,
                    'token' => $this->attachDevice($existing),
                    'created' => false,
                ];
            }

            $this->tableStatus->recalculate($table);

            return [
                'session' => $session,
                'token' => $this->attachDevice($session),
                'created' => true,
            ];
        });
    }

    /**
     * Sessionni yopish. To'lov `PaymentService` da (PHASE 12) — bu yerda
     * faqat holat o'zgarishi va stolni bo'shatish.
     */
    public function closeSession(TableSession $session): TableSession
    {
        return DB::transaction(function () use ($session): TableSession {
            $session->update([
                'status' => SessionStatus::CLOSED,
                'closed_at' => now(),
            ]);

            // `active_key` endi NULL — stol yangi session uchun bo'shadi.
            $this->tableStatus->recalculate($session->table);

            return $session->refresh();
        });
    }

    /**
     * DRAFT uchun token — sessionga bog'lanmagan.
     *
     * `session_devices` ga YOZILMAYDI (u yerda session FK majburiy).
     * Faqat `orders.created_by_token_hash` da yashaydi; to'lovdan keyin
     * yangi sessionga o'tganda qurilma sifatida qo'shiladi (PHASE 12).
     */
    public function issueDraftToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    /** Qurilmani mavjud sessionga biriktiradi — draft chiqqanda (PHASE 12). */
    public function attachToken(TableSession $session, string $token): void
    {
        SessionDevice::firstOrCreate(
            ['customer_token_hash' => SessionDevice::hashToken($token)],
            ['table_session_id' => $session->id, 'last_seen_at' => now()],
        );
    }

    /** Token bo'yicha qurilmani topish — hash orqali (docs/05 §2.3). */
    public function findByCustomerToken(string $token): ?TableSession
    {
        $device = SessionDevice::query()
            ->with('session')
            ->where('customer_token_hash', SessionDevice::hashToken($token))
            ->first();

        return $device?->session;
    }

    /**
     * Qurilmani sessionga biriktiradi va PLAINTEXT tokenni qaytaradi.
     *
     * ⚠️ Token DB'da SHA-256 hash sifatida saqlanadi (docs/05-PHASE0-PLAN.md
     * §2.3). bcrypt EMAS: token bo'yicha `where` qidiruv kerak, bcrypt esa
     * har safar boshqa hash beradi va indeks ishlamaydi. Token allaqachon
     * 64 belgi kriptografik random, shuning uchun salt/stretching
     * kerak emas — bruteforce imkonsiz.
     */
    private function attachDevice(TableSession $session): string
    {
        $token = Str::random(self::TOKEN_LENGTH);

        SessionDevice::create([
            'table_session_id' => $session->id,
            'customer_token_hash' => SessionDevice::hashToken($token),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
            'ip_address' => request()->ip(),
            'last_seen_at' => now(),
        ]);

        return $token;
    }

    private function publicId(): string
    {
        return Str::random(32);
    }

    /** @return list<string> */
    private function occupyingValues(): array
    {
        return array_map(
            static fn (SessionStatus $status): string => $status->value,
            SessionStatus::occupying(),
        );
    }

    /** Table status hisoblanishi uchun ochiq. */
    public function tableStatusFor(Table $table): TableStatus
    {
        return $this->tableStatus->calculate($table);
    }
}
