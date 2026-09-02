<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/01-ARCHITECTURE.md §5 + docs/02-I18N-RU-UZ.md §3.
 *
 * Nomlar IKKI USTUNDA (JSON emas) — qidiruv va indeks oson.
 * Admin panelda ikkala til ham MAJBURIY maydon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')->cascadeOnDelete();

            $table->string('name_ru', 120);
            $table->string('name_uz', 120);
            $table->string('slug', 140);
            $table->string('image', 255)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
