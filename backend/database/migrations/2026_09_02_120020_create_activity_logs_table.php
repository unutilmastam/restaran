<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5, §13 + docs/07-DB-DECISIONS.md §1.
 *
 * ⚠️ `restaurant_id` NULLABLE — bu 3 ta istisnodan biri, ikki sababga ko'ra:
 *
 * 1. SUPER_ADMIN amallarining bir qismida restoran UMUMAN YO'Q:
 *    `plans` narxini o'zgartirish, platforma `settings` ini tahrirlash,
 *    yangi restoran yaratish (yozuv paytida restoran hali yo'q).
 *
 * 2. docs/06-SAAS.md §11 — butunlay o'chirish audit yozuvi.
 *    CASCADE bo'lsa restoran o'chganda "kim o'chirdi" yozuvining O'ZI ham
 *    o'chib ketardi. Shuning uchun FK `ON DELETE SET NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')->nullable()
                ->constrained('restaurants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('action', 60);
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Retention: 90 kun (docs/05-PHASE0-PLAN.md §0) — 1 GB disk.
            $table->index(['restaurant_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
