<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/06-SAAS.md §2 (javob 1).
 *
 * PLATFORMA darajasi — `restaurant_id` YO'Q, barcha restoranlar uchun umumiy.
 * Narxlar KODDA yozilmaydi, faqat shu yerda; SUPER_ADMIN tahrirlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();
            $table->string('name_ru', 120);
            $table->string('name_uz', 120);
            $table->unsignedSmallInteger('days');
            // docs/07-DB-DECISIONS.md §5 — DECIMAL, hech qachon FLOAT emas.
            $table->decimal('price', 12, 2)->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
