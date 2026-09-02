<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5 + docs/06-SAAS.md §2.
 *
 * Tenant jadvali — `restaurant_id` yo'q, o'zi tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            // docs/06-SAAS.md §7 — menu.itcode.uz/r/{slug}/t/{nfc_token}
            $table->string('slug', 80)->unique();
            $table->string('phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            // Restoran egasi o'zi yuklaydi (docs/06 §9, javob 8).
            $table->string('logo', 255)->nullable();

            $table->char('currency', 3)->default('UZS');
            $table->string('default_locale', 2)->default('uz');
            // Hisobotlarda "kun" chegarasi SHU timezone'da hisoblanadi.
            $table->string('timezone', 64)->default('Asia/Tashkent');

            $table->boolean('is_active')->default(true);

            // --- SaaS: obuna (docs/06-SAAS.md §2, §3) ---
            $table->string('subscription_status', 16)
                ->default(SubscriptionStatus::TRIAL->value);
            // Sana emas, aniq VAQT: 14:00 da to'lasa 14:00 da tugaydi (§3).
            $table->dateTime('expires_at')->nullable();
            $table->string('suspended_reason', 255)->nullable();

            // Restoran EGASINING aloqasi — SUPER_ADMIN u bilan bog'lanadi.
            // Platformaning aloqa ma'lumotlari `settings` da (docs/06 §12).
            $table->string('owner_phone', 32)->nullable();
            $table->string('owner_telegram', 64)->nullable();
            // "Telegramni ulash" bosilgach to'ladi (docs/06 §6, PHASE 13.6).
            $table->string('owner_telegram_chat_id', 64)->nullable();

            // --- SaaS: limitlar (docs/06 §8, javob 7) ---
            // Limit TARIFGA emas, RESTORANGA biriktiriladi. Bu boshlang'ich
            // qiymat; keyin SUPER_ADMIN har restoran uchun o'zgartiradi.
            $table->unsignedSmallInteger('max_tables')->default(30);
            $table->unsignedSmallInteger('max_products')->default(100);
            $table->unsignedSmallInteger('max_waiters')->default(10);

            $table->timestamps();
            // ARXIVLASH — docs/06-SAAS.md §11, default o'chirish usuli.
            $table->softDeletes();

            $table->index(['subscription_status', 'expires_at']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
