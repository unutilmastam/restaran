<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderCounter;
use App\Models\Restaurant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kunlik buyurtma raqami — docs/05-PHASE0-PLAN.md §2.5 (javob 5).
 *
 * `MAX(order_number) + 1` shared hostingda ikki bir vaqtli so'rovda
 * DUBLIKAT beradi. Shuning uchun alohida hisoblagich jadval va
 * `lockForUpdate()`.
 *
 * ⚠️ Bu metod HAR DOIM chaqiruvchi transaction ICHIDA ishlatiladi —
 * lock transaction tugagunicha ushlab turilishi kerak.
 */
class OrderNumberService
{
    /** Kunlik raqam `#0001` shaklida — admin "42-order" deb aytishi qulay. */
    public function next(Restaurant $restaurant, ?Carbon $businessDate = null): array
    {
        $date = ($businessDate ?? $this->businessDate($restaurant))->toDateString();

        // Qator bo'lmasa yaratamiz.
        //
        // ⚠️ `firstOrCreate` ATOMAR EMAS: u avval SELECT, keyin INSERT
        // qiladi. Kunning birinchi buyurtmalari bir vaqtda kelsa bir
        // nechta process bir xil qatorni yaratishga urinadi va
        // UNIQUE(restaurant_id, business_date) ularni rad etadi.
        // Buni concurrency testi topdi — 8 ta paralel orderdan 2 tasi
        // yiqilgan edi.
        //
        // Yechim: xatoni jim yutamiz. Demak qator boshqa process
        // tomonidan yaratilgan — bizga aynan shu kerak edi.
        try {
            OrderCounter::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurant->id,
                'business_date' => $date,
                'last_number' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Qator allaqachon bor — davom etamiz.
        }

        // Endi qatorni QULFLAB olamiz — parallel so'rovlar navbatga turadi.
        $counter = OrderCounter::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('business_date', $date)
            ->lockForUpdate()
            ->firstOrFail();

        $number = $counter->last_number + 1;

        DB::table('order_counters')
            ->where('id', $counter->id)
            ->update(['last_number' => $number, 'updated_at' => now()]);

        return [
            'business_date' => $date,
            'order_number' => sprintf('#%04d', $number),
        ];
    }

    /**
     * "Kun" chegarasi restoran timezone'ida — UTC'da emas. Toshkentda
     * soat 01:00 da berilgan buyurtma o'sha kunning raqamini olishi kerak.
     */
    public function businessDate(Restaurant $restaurant): Carbon
    {
        return now()->timezone($restaurant->timezone ?: 'UTC')->startOfDay();
    }
}
