<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/05-PHASE0-PLAN.md §2.5 — `order_number` uchun race-safe hisoblagich.
 *
 * `MAX(order_number) + 1` shared hostingda ikki bir vaqtli so'rovda
 * dublikat beradi. OrderNumberService shu qatorni transaction ichida
 * lockForUpdate() bilan oladi.
 *
 * Raqam HAR KUNI 1 dan boshlanadi (javob 5) — admin "42-order" deb
 * aytishi qulay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_counters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();

            // Restoran timezone'idagi kun (UTC emas!) — hisobot chegarasi
            // bilan bir xil bo'lishi uchun.
            $table->date('business_date');
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->unique(['restaurant_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_counters');
    }
};
