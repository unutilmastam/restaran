<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Afitsant va qo'shimcha adminlar uchun.
 *
 * ⚠️ `role` da faqat ADMIN va WAITER. `OWNER_ADMIN` bu yerdan
 * berilmaydi — u restoran yaratilganda bir marta qo'yiladi va faqat
 * SUPER_ADMIN o'zgartira oladi (docs/06-SAAS.md §1, javob 4).
 */
class StaffRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('staff');
        $creating = $id === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('users', 'username')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->ignore($id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:6', 'max:190'],
            'pin' => ['nullable', 'string', 'min:4', 'max:12'],
            'role' => ['required', Rule::in([UserRole::ADMIN->value, UserRole::WAITER->value])],
            'locale' => ['nullable', 'in:uz,ru'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
