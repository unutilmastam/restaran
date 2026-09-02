<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\RestaurantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 5 kategoriya + 25 mahsulot — `docs/03-PHASES.md` PHASE 2 talabi.
 *
 * ⚠️ `DatabaseSeeder` ICHIDA CHAQIRILMAYDI. Yangi restoran BO'SH MENYU
 * bilan boshlanadi (docs/06-SAAS.md §9, javob 8) — egasi o'zi to'ldiradi.
 * Bu seeder faqat test va local development uchun, qo'lda chaqiriladi:
 *
 *     php artisan db:seed --class=MenuDemoSeeder
 *
 * Rasm yo'q: `image = null`. Frontend placeholder ko'rsatadi — bu real
 * holat, chunki restoran egasi hamma mahsulotga rasm qo'ymasligi mumkin.
 */
class MenuDemoSeeder extends Seeder
{
    /** @var array<string, array{ru: string, uz: string, products: list<array{ru: string, uz: string, price: int, weight?: int, time?: int, discount?: int, available?: bool, desc_ru?: string, desc_uz?: string}>}> */
    private const MENU = [
        'salads' => [
            'ru' => 'Салаты', 'uz' => 'Salatlar',
            'products' => [
                ['ru' => 'Ачичук', 'uz' => 'Achichuk', 'price' => 18000, 'weight' => 200,
                    'desc_ru' => 'Помидоры, лук, зелень', 'desc_uz' => 'Pomidor, piyoz, ko\'kat'],
                ['ru' => 'Оливье', 'uz' => 'Olivye', 'price' => 32000, 'weight' => 250],
                ['ru' => 'Цезарь с курицей', 'uz' => 'Tovuqli Sezar', 'price' => 45000, 'weight' => 220,
                    'desc_ru' => 'Курица, пармезан, соус', 'desc_uz' => 'Tovuq, parmezan, sous'],
                ['ru' => 'Греческий', 'uz' => 'Grek salati', 'price' => 38000, 'weight' => 230],
                ['ru' => 'Ташкент', 'uz' => 'Toshkent salati', 'price' => 42000, 'weight' => 240],
            ],
        ],
        'hot' => [
            'ru' => 'Горячие блюда', 'uz' => 'Issiq taomlar',
            'products' => [
                ['ru' => 'Плов', 'uz' => 'Osh', 'price' => 45000, 'weight' => 350, 'time' => 20,
                    'desc_ru' => 'Говядина, рис, морковь', 'desc_uz' => 'Mol go\'shti, guruch, sabzi'],
                ['ru' => 'Лагман', 'uz' => 'Lagmon', 'price' => 42000, 'weight' => 400, 'time' => 25],
                ['ru' => 'Шурпа', 'uz' => 'Shurva', 'price' => 38000, 'weight' => 400, 'time' => 30],
                ['ru' => 'Манты (5 шт)', 'uz' => 'Manti (5 dona)', 'price' => 40000, 'weight' => 300, 'time' => 35],
                ['ru' => 'Димляма', 'uz' => 'Dimlama', 'price' => 48000, 'weight' => 380, 'time' => 30],
            ],
        ],
        'grill' => [
            'ru' => 'Шашлык', 'uz' => 'Shashlik',
            'products' => [
                ['ru' => 'Шашлык из говядины', 'uz' => 'Mol go\'shti shashlik', 'price' => 35000, 'weight' => 150, 'time' => 20],
                ['ru' => 'Шашлык из баранины', 'uz' => 'Qo\'y go\'shti shashlik', 'price' => 40000, 'weight' => 150, 'time' => 20],
                ['ru' => 'Куриный шашлык', 'uz' => 'Tovuq shashlik', 'price' => 28000, 'weight' => 150, 'time' => 15],
                ['ru' => 'Люля-кебаб', 'uz' => 'Lyula-kabob', 'price' => 32000, 'weight' => 160, 'time' => 20],
                // Vaqtincha tugagan — menyuda ko'rinadi, lekin buyurtma qilinmaydi.
                ['ru' => 'Рыба на гриле', 'uz' => 'Panjarada baliq', 'price' => 65000, 'weight' => 250,
                    'time' => 30, 'available' => false],
            ],
        ],
        'drinks' => [
            'ru' => 'Напитки', 'uz' => 'Ichimliklar',
            'products' => [
                ['ru' => 'Чай чёрный', 'uz' => 'Qora choy', 'price' => 8000, 'weight' => 500],
                ['ru' => 'Чай зелёный', 'uz' => 'Ko\'k choy', 'price' => 8000, 'weight' => 500],
                ['ru' => 'Айран', 'uz' => 'Ayron', 'price' => 12000, 'weight' => 300],
                ['ru' => 'Кока-кола 0.5', 'uz' => 'Koka-kola 0.5', 'price' => 15000, 'weight' => 500],
                ['ru' => 'Вода без газа 0.5', 'uz' => 'Gazsiz suv 0.5', 'price' => 6000, 'weight' => 500],
            ],
        ],
        'desserts' => [
            'ru' => 'Десерты', 'uz' => 'Shirinliklar',
            'products' => [
                ['ru' => 'Чак-чак', 'uz' => 'Chak-chak', 'price' => 22000, 'weight' => 150],
                ['ru' => 'Медовик', 'uz' => 'Medovik', 'price' => 28000, 'weight' => 140],
                ['ru' => 'Наполеон', 'uz' => 'Napoleon', 'price' => 28000, 'weight' => 140, 'discount' => 10],
                ['ru' => 'Мороженое', 'uz' => 'Muzqaymoq', 'price' => 18000, 'weight' => 100],
                ['ru' => 'Фруктовая тарелка', 'uz' => 'Mevalar likopchasi', 'price' => 55000, 'weight' => 500],
            ],
        ],
    ];

    public function run(): void
    {
        RestaurantContext::allowCrossRestaurant();

        $restaurant = Restaurant::where('slug', 'demo')->first();

        if ($restaurant === null) {
            $this->command?->warn('Demo restoran topilmadi — avval DatabaseSeeder ni yuriting.');
            RestaurantContext::reset();

            return;
        }

        $categoryOrder = 0;

        foreach (self::MENU as $slug => $group) {
            $categoryOrder++;

            $category = Category::withoutGlobalScopes()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => $slug],
                [
                    'name_ru' => $group['ru'],
                    'name_uz' => $group['uz'],
                    'sort_order' => $categoryOrder,
                    'is_active' => true,
                ],
            );

            foreach ($group['products'] as $order => $item) {
                Product::withoutGlobalScopes()->updateOrCreate(
                    [
                        'restaurant_id' => $restaurant->id,
                        'category_id' => $category->id,
                        'name_uz' => $item['uz'],
                    ],
                    [
                        'name_ru' => $item['ru'],
                        'description_ru' => $item['desc_ru'] ?? null,
                        'description_uz' => $item['desc_uz'] ?? null,
                        // Rasm ATAYIN yo'q — frontend placeholder ko'rsatadi.
                        'image' => null,
                        'price' => $item['price'],
                        'discount' => $item['discount'] ?? 0,
                        'weight' => $item['weight'] ?? null,
                        'preparation_time' => $item['time'] ?? null,
                        'is_available' => $item['available'] ?? true,
                        'is_active' => true,
                        'sort_order' => $order + 1,
                    ],
                );
            }
        }

        RestaurantContext::reset();

        $this->command?->info(sprintf(
            '%d kategoriya, %d mahsulot qo\'shildi (%s).',
            count(self::MENU),
            array_sum(array_map(static fn (array $g): int => count($g['products']), self::MENU)),
            $restaurant->name,
        ));
    }
}
