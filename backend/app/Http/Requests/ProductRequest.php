<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * docs/02-I18N-RU-UZ.md §3 — `name_uz` VA `name_ru` ikkalasi ham
 * MAJBURIY maydon.
 */
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => [
                'required', 'integer',
                // Boshqa restoranning kategoriyasi tanlanmasin.
                Rule::exists('categories', 'id')
                    ->where('restaurant_id', $this->user()->restaurant_id),
            ],
            'name_uz' => ['required', 'string', 'max:150'],
            'name_ru' => ['required', 'string', 'max:150'],
            'description_uz' => ['nullable', 'string', 'max:2000'],
            'description_ru' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            // FOIZ (0-100), summa emas (javob 6).
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'preparation_time' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_available' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_uz' => __('validation.attributes.name_uz'),
            'name_ru' => __('validation.attributes.name_ru'),
            'price' => __('validation.attributes.price'),
        ];
    }
}
