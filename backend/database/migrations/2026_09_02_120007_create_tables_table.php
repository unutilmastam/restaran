<?php

declare(strict_types=1);

use App\Enums\TableStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5.
 *
 * ⚠️ `tables` va `table_sessions` HECH QACHON birlashtirilmaydi
 * (CLAUDE.md §2.3). Stol doimiy — NFC tag unga yopishtirilgan;
 * session vaqtinchalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();

            $table->unsignedSmallInteger('number');
            $table->string('name', 80)->nullable();
            $table->unsignedTinyInteger('capacity')->default(4);

            // NFC tagning fizik UID'i — faqat ma'lumot uchun.
            $table->string('nfc_uid', 64)->nullable();
            // URL'da SHU ishlatiladi, table_id EMAS (docs/01 §4).
            // Random, taxmin qilib bo'lmaydigan.
            $table->string('nfc_token', 64)->unique();

            // ⚠️ DENORMALIZATSIYA — yagona yozuvchi TableStatusService.
            $table->string('status', 20)->default(TableStatus::AVAILABLE->value);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'number']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
