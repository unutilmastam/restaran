<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/02-I18N-RU-UZ.md §3 — IKKALA til ham MAJBURIY. */
class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('category');

        return [
            'name_uz' => ['required', 'string', 'max:120'],
            'name_ru' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:140', 'alpha_dash',
                Rule::unique('categories', 'slug')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->ignore($id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_uz' => __('validation.attributes.name_uz'),
            'name_ru' => __('validation.attributes.name_ru'),
        ];
    }
}
