<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/01-ARCHITECTURE.md §5, §8 + docs/05-PHASE0-PLAN.md §2.4. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('table_id')
                ->constrained('tables')->cascadeOnDelete();

            // NULLABLE — DRAFT order uchun (docs/01 §12).
            // Mijoz WAITING_PAYMENT stolga buyurtma bersa, order session'siz
            // saqlanadi va to'lovdan keyin yangi sessionga biriktiriladi.
            $table->foreignId('session_id')->nullable()
                ->constrained('table_sessions')->cascadeOnDelete();

            // Waiter o'chsa buyurtma tarixi qolsin.
            $table->foreignId('waiter_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Idempotency (CLAUDE.md §3.1) — tugmani ikki marta bosish
            // bitta order yaratadi.
            $table->uuid('client_order_uuid');
            // Kunlik: #0001, #0002... (javob 5)
            $table->string('order_number', 12);
            $table->date('business_date');

            $table->string('status', 24)->default(OrderStatus::PENDING->value);
            $table->unsignedTinyInteger('guest_count')->default(1);

            // Hammasi BACKENDDA hisoblanadi (CLAUDE.md §2.6, §2.7).
            $table->decimal('subtotal', 12, 2)->unsigned()->default(0);
            // Admin qo'yadigan SUMMA (foiz emas) — javob 6.
            $table->decimal('discount', 12, 2)->unsigned()->default(0);
            $table->decimal('total', 12, 2)->unsigned()->default(0);

            // Mijoz izohi ("achchiq qilmang").
            $table->string('note', 255)->nullable();

            // DRAFT muddati (javob 7: 120 daqiqa). Muddati o'tgani EXPIRED
            // bo'ladi — uzoq turgan cart to'lovdan keyin "tirilib" ketmasin.
            $table->dateTime('draft_expires_at')->nullable();

            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('waiter_accepted_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            // Idempotency restoran ichida (docs/05 §2.6).
            $table->unique(['restaurant_id', 'client_order_uuid']);
            $table->unique(['restaurant_id', 'business_date', 'order_number']);

            $table->index(['session_id', 'status']);
            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['waiter_id', 'status']);
            // DRAFT larni stol bo'yicha topish (to'lovdan keyin biriktirish).
            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
