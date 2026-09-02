<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/06-SAAS.md §12 — IKKI DARAJALI sozlamalar.
 *
 *   restaurant_id = NULL  → PLATFORMA sozlamasi (SUPER_ADMIN)
 *                           contact_phone, contact_telegram, contact_note_*
 *   restaurant_id = 5     → restoran sozlamasi (restoran admini)
 *                           voice_enabled, voice_volume, ...
 *
 * Bu `restaurant_id` nullable bo'lgan 3 ta jadvaldan biri (docs/07 §1) va
 * global scope'da `orWhereNull` ishlatiladigan YAGONA jadval (docs/07 §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')->nullable()
                ->constrained('restaurants')->cascadeOnDelete();

            $table->string('key', 100);
            $table->text('value')->nullable();

            $table->timestamps();

            $table->unique(['restaurant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
