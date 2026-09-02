<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5 + docs/06-SAAS.md §1.
 *
 * `restaurant_id` NULLABLE — bu 3 ta istisnodan biri (docs/07 §1):
 * SUPER_ADMIN hech qaysi restoranga tegishli emas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // NULL = SUPER_ADMIN (docs/06-SAAS.md §1).
            $table->foreignId('restaurant_id')->nullable()
                ->constrained('restaurants')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('username', 60);
            $table->string('phone', 32)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('password');
            // Waiter telefonida tez kirish uchun — HASH qilib saqlanadi.
            $table->string('pin')->nullable();

            $table->string('role', 20)->default(UserRole::WAITER->value);
            // Waiter uchun: FREE | BUSY | OFFLINE (docs/01 §3).
            $table->string('status', 16)->nullable();
            // WaiterAssignmentService: teng yukda eng eski bo'shagani tanlanadi.
            $table->dateTime('last_free_at')->nullable();

            $table->string('locale', 2)->default('uz');
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Login restoran ichida unique (turli restoranlarda bir xil
            // username bo'lishi mumkin).
            $table->unique(['restaurant_id', 'username']);
            $table->index(['restaurant_id', 'role', 'status']);
            $table->index(['restaurant_id', 'status', 'last_free_at']);
        });

        /*
         * docs/06-SAAS.md §1 — har restoranda AYNAN BITTA OWNER_ADMIN.
         *
         * Generated column + UNIQUE: OWNER_ADMIN bo'lmagan qatorlarda NULL,
         * NULL lar unique indexda takrorlanaveradi. Ikkinchi OWNER_ADMIN
         * qo'shishga urinish DB darajasida rad etiladi — kod xatosi ham
         * bu qoidani buza olmaydi (docs/07-DB-DECISIONS.md §6).
         *
         * STORED, VIRTUAL emas: MariaDB VIRTUAL ustunda UNIQUE ga ruxsat
         * bermaydi, STORED esa MySQL 8 va MariaDB da bir xil ishlaydi.
         */
        $ownerAdmin = UserRole::OWNER_ADMIN->value;
        DB::statement(<<<SQL
            ALTER TABLE `users`
              ADD COLUMN `owner_admin_key` BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                  CASE WHEN `role` = '{$ownerAdmin}' THEN `restaurant_id` END
                ) STORED,
              ADD UNIQUE KEY `users_owner_admin_key_unique` (`owner_admin_key`)
        SQL);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Redis yo'q — SESSION_DRIVER=database (docs/05-PHASE0-PLAN.md §0).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
