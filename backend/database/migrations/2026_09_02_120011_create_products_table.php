<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** docs/01-ARCHITECTURE.md §5 + docs/02-I18N-RU-UZ.md §3. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')->cascadeOnDelete();

            $table->string('name_ru', 150);
            $table->string('name_uz', 150);
            $table->text('description_ru')->nullable();
            $table->text('description_uz')->nullable();

            // DB'da faqat URL — binary saqlanmaydi (docs/01 §13).
            // ImageService webp'ga o'giradi va o'lchamini cheklaydi (1 GB disk).
            $table->string('image', 255)->nullable();

            $table->decimal('price', 12, 2)->unsigned();
            // FOIZ (0-100), summa emas — docs/05-PHASE0-PLAN.md §5 javob 6.
            $table->unsignedTinyInteger('discount')->default(0);

            $table->unsignedSmallInteger('weight')->nullable();
            $table->unsignedSmallInteger('preparation_time')->nullable();

            // Vaqtincha tugagan (oshxonada yo'q).
            $table->boolean('is_available')->default(true);
            // Menyudan olib qo'yilgan.
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'category_id', 'is_active', 'is_available'], 'products_menu_index');
            $table->index(['restaurant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
