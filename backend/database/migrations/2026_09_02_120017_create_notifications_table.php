<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5, §11.
 *
 * ⚠️ Laravel'ning o'z `notifications` jadvali EMAS — bu custom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            // NULL = restorandagi barcha adminlarga.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();

            $table->string('type', 32);

            // Ikkala til ham saqlanadi — admin tilni almashtirsa
            // bildirishnomalar qayta yaratilmasin (docs/02 §4).
            $table->string('title_ru', 190);
            $table->string('title_uz', 190);
            $table->string('message_ru', 500)->nullable();
            $table->string('message_uz', 500)->nullable();

            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            $table->boolean('is_read')->default(false);
            // docs/01 §11 — bir event = bir marta ovoz. Sahifa refresh
            // bo'lsa eski bildirishnoma QAYTA O'QILMAYDI.
            $table->boolean('voice_played')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'user_id', 'is_read']);
            $table->index(['restaurant_id', 'voice_played', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
