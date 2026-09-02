<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/06-SAAS.md §12 — IKKI DARAJALI.
 *
 * ⚠️ `restaurant_id IS NULL` yozuvlarini ham ko'rsatadigan YAGONA model
 * (docs/07-DB-DECISIONS.md §2). Boshqa modelga tarqalmasligini PHASE 14
 * testi qulflaydi.
 */
class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use BelongsToRestaurant, HasFactory;

    protected $guarded = ['id'];

    protected static function allowsPlatformRows(): bool
    {
        return true;
    }

    /** Platforma sozlamasi (SUPER_ADMIN boshqaradi). */
    public function isPlatformLevel(): bool
    {
        return $this->restaurant_id === null;
    }
}
