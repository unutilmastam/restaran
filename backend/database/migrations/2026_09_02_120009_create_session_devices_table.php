<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/05-PHASE0-PLAN.md §5 (javob 9): bitta stolda bir necha telefon
 * BITTA sessionni bo'lishadi — har qurilma o'z tokenini oladi.
 *
 * `restaurant_id` YO'Q — `table_sessions` ning bolasi, izolyatsiyani
 * ota-jadvaldan meros oladi (docs/07-DB-DECISIONS.md §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_session_id')
                ->constrained('table_sessions')->cascadeOnDelete();

            // ⚠️ Token OCHIQ MATNDA saqlanmaydi — bu bearer token
            // (docs/05-PHASE0-PLAN.md §2.3). Mijozga plaintext faqat
            // bir marta, yaratilganda qaytariladi.
            $table->char('customer_token_hash', 64)->unique();

            $table->string('user_agent', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('last_seen_at')->nullable();

            $table->timestamps();

            $table->index('table_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_devices');
    }
};
