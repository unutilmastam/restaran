<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5 — MIJOZNING stol hisobi to'lovi.
 *
 * ⚠️ Bu `subscription_payments` bilan aralashtirilmaydi: u restoranning
 * PLATFORMAGA to'lovi, bu esa mijozning RESTORANGA to'lovi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('session_id')
                ->constrained('table_sessions')->cascadeOnDelete();

            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('method', 16);
            $table->string('status', 16)->default(PaymentStatus::PENDING->value);

            $table->dateTime('paid_at')->nullable();
            // Kassir/admin o'chsa moliyaviy yozuv qolsin.
            $table->foreignId('received_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('transaction_reference', 190)->nullable();

            $table->timestamps();

            $table->index('session_id');
            // Revenue hisoboti uchun (docs/01 §14).
            $table->index(['restaurant_id', 'status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
