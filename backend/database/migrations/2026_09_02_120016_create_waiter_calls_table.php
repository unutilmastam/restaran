<?php

declare(strict_types=1);

use App\Enums\WaiterCallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/01-ARCHITECTURE.md §5 + PHASE 11. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_calls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('table_id')
                ->constrained('tables')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()
                ->constrained('table_sessions')->cascadeOnDelete();

            // Chaqiruvni MIJOZ qiladi — u user emas. Shuning uchun
            // polimorf: CUSTOMER (id yo'q) yoki USER (docs/05 §2.6).
            $table->string('created_by_type', 12)->default('CUSTOMER');
            $table->foreignId('created_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('assigned_waiter_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status', 16)->default(WaiterCallStatus::PENDING->value);
            $table->string('message', 255)->nullable();

            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['assigned_waiter_id', 'status']);
            // Spam himoyasi: bir stoldan 2 daqiqada 1 marta.
            $table->index(['table_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_calls');
    }
};
