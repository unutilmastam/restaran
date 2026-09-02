<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required_without:pin', 'nullable', 'string', 'max:190'],
            'pin' => ['required_without:password', 'nullable', 'string', 'max:12'],
        ];
    }
}
