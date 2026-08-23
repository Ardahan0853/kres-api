<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'E-posta adresi gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi gir.',
            'password.required' => 'Şifre gerekli.',
            'device_name.required' => 'Cihaz adı gerekli.',
        ];
    }
}
