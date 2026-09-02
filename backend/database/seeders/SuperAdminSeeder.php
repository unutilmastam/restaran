<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/** docs/06-SAAS.md §1 — platforma egasi, `restaurant_id = null`. */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => null, 'username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'super@smart-restaurant.local',
                // ⚠️ Faqat local development uchun. Production'da birinchi
                // kirishda majburiy o'zgartiriladi.
                'password' => 'password',
                'role' => UserRole::SUPER_ADMIN,
                'locale' => 'uz',
                'is_active' => true,
            ],
        );
    }
}
