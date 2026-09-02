<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * docs/06-SAAS.md §12 (javob 6) — PLATFORMA sozlamalari.
 *
 * `restaurant_id = null`. To'lov sahifasi shu qiymatlarni ko'rsatadi,
 * kodda hardcode qilinmaydi. SUPER_ADMIN panelidan to'ldiriladi.
 */
class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'contact_phone' => '',
            'contact_telegram' => '',
            'contact_note_ru' => '',
            'contact_note_uz' => '',
        ];

        foreach ($settings as $key => $value) {
            Setting::withoutGlobalScopes()->updateOrCreate(
                ['restaurant_id' => null, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
