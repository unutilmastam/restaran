<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5 + CLAUDE.md §3.3.
 *
 * `restaurant_id` YO'Q — `orders` ning bolasi (docs/07-DB-DECISIONS.md §1).
 *
 * SNAPSHOT: mahsulot nomi va narxi buyurtma paytidagi holicha saqlanadi.
 * Narx keyin o'zgarsa ham chek va hisobot buzilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')->cascadeOnDelete();

            // ⚠️ RESTRICT — mahsulotni tasodifan o'chirib, buyurtma tarixini
            // buzib qo'yishdan himoya. Restoranni butunlay o'chirishda
            // RestaurantPurgeService aniq tartibda o'chiradi (docs/07 §4).
            $table->foreignId('product_id')
                ->constrained('products')->restrictOnDelete();

            // Ikkala til ham saqlanadi (docs/02-I18N-RU-UZ.md §3).
            $table->string('product_name_ru_snapshot', 150);
            $table->string('product_name_uz_snapshot', 150);
            $table->decimal('price_snapshot', 12, 2)->unsigned();

            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 12, 2)->unsigned();
            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index('order_id');
            // Hisobotdagi TOP-10 mahsulot uchun.
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
