<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/06-SAAS.md §2. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()
                ->constrained('plans')->nullOnDelete();

            $table->string('status', 16)->default(SubscriptionStatus::TRIAL->value);
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->decimal('amount', 12, 2)->unsigned()->default(0);

            // SUPER_ADMIN. User o'chsa tarix qolsin.
            $table->foreignId('activated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
