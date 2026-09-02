<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ⚠️ `nfc_token` QABUL QILINMAYDI — u har doim serverda generatsiya
 * qilinadi (docs/01-ARCHITECTURE.md §4: random, taxmin qilib bo'lmaydigan).
 */
class TableRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'number' => [
                'required', 'integer', 'min:1', 'max:9999',
                Rule::unique('tables', 'number')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->ignore($this->route('table')),
            ],
            'name' => ['nullable', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
