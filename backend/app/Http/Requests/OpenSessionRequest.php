<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ⚠️ `nfc_token` bu yerda YO'Q — u route parametridan olinadi va
 * `ResolveTableByNfcToken` middleware tekshiradi. Mijoz `restaurant_id`,
 * `table_id` yoki `status` yubora olmasligi uchun ular ham qabul
 * qilinmaydi (docs/01-ARCHITECTURE.md §13).
 */
class OpenSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'guest_count' => __('validation.attributes.guest_count'),
        ];
    }
}
