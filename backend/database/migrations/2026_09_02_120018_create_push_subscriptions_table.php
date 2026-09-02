<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waiter PWA push notification (docs/03-PHASES.md PHASE 7).
 *
 * `restaurant_id` YO'Q — `users` ning bolasi (docs/07-DB-DECISIONS.md §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('endpoint', 500);
            $table->string('public_key', 190)->nullable();
            $table->string('auth_token', 190)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            // endpoint 500 belgi — to'liq unique index sig'maydi,
            // shuning uchun hash bo'yicha unique.
            $table->char('endpoint_hash', 64)->unique();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
