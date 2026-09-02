<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/06-SAAS.md §2 + docs/07-DB-DECISIONS.md §3.
 *
 * SNAPSHOT jadvali. `plans.price` yoki `plans.name_*` o'zgarsa, bu yerdagi
 * yozuvlar O'ZGARMASLIGI SHART — aks holda to'lov tarixi va moliyaviy
 * hisobot buziladi.
 *
 * Shuning uchun UI va hisobot HECH QACHON `plans` ga JOIN qilmaydi —
 * har doim `*_snapshot` ustunlari ishlatiladi. `plan_id` faqat ma'lumot
 * uchun (qaysi tarif ekanini bilish), summa manbai emas.
 *
 * `updated_at` ATAYIN yo'q: to'lov yozuvi faqat yaratiladi, hech qachon
 * tahrirlanmaydi (model darajasida ham bloklangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()
                ->constrained('subscriptions')->nullOnDelete();
            // RESTRICT: to'lovi bor tarifni o'chirib bo'lmaydi
            // (o'rniga is_active = false qilinadi).
            $table->foreignId('plan_id')->nullable()
                ->constrained('plans')->restrictOnDelete();

            // --- SNAPSHOT: bu 5 ta ustun hech qachon o'zgarmaydi ---
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('plan_code_snapshot', 32);
            $table->string('plan_name_ru_snapshot', 120);
            $table->string('plan_name_uz_snapshot', 120);
            $table->unsignedSmallInteger('plan_days_snapshot');

            $table->string('method', 16);
            $table->string('reference', 190)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
