<?php

declare(strict_types=1);

use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** docs/01-ARCHITECTURE.md §5 + docs/07-DB-DECISIONS.md §6. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('table_id')
                ->constrained('tables')->cascadeOnDelete();

            $table->unsignedTinyInteger('guest_count')->default(1);
            $table->string('status', 20)->default(SessionStatus::ACTIVE->value);

            // Broadcast kanal nomi uchun — nfc_token EMAS.
            // nfc_token stolga doimiy yopishtirilgan; agar kanal nomi
            // shundan yasalsa, bir marta skanerlagan odam stolning
            // kelajakdagi barcha buyurtmalarini eshitib turardi.
            $table->string('public_id', 40)->unique();

            $table->decimal('total_amount', 12, 2)->unsigned()->default(0);
            $table->decimal('paid_amount', 12, 2)->unsigned()->default(0);

            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();

            $table->timestamps();

            $table->index(['table_id', 'status']);
            $table->index(['restaurant_id', 'status', 'opened_at']);
        });

        /*
         * docs/07-DB-DECISIONS.md §6 — bitta stolda bir vaqtda FAQAT BITTA
         * ochiq session.
         *
         * ACTIVE va WAITING_PAYMENT da qiymat = table_id, qolganda NULL.
         * NULL lar unique indexda takrorlanaveradi, shuning uchun bitta
         * stolning yuzlab yopilgan sessioni bemalol yashaydi.
         *
         * Bu OXIRGI himoya chizig'i. Yaxshi UX uchun SessionService
         * `tables` qatorini lockForUpdate() bilan oladi va ikkinchi
         * so'rovga mavjud sessionni qaytaradi (409 emas).
         *
         * SessionStatus::occupying() bu ro'yxatning yagona manbai —
         * u o'zgarsa shu migration ham o'zgarishi kerak.
         */
        $occupying = implode(',', array_map(
            static fn (SessionStatus $status): string => "'{$status->value}'",
            SessionStatus::occupying(),
        ));

        DB::statement(<<<SQL
            ALTER TABLE `table_sessions`
              ADD COLUMN `active_key` BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                  CASE WHEN `status` IN ({$occupying}) THEN `table_id` END
                ) STORED,
              ADD UNIQUE KEY `table_sessions_active_key_unique` (`active_key`)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
