<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            // Girisin yapildigi an; sunucu saatiyle ezilmez.
            'started_at' => ['required', 'date'],
            // Bilerek GEVSEK: alan gelmezse false sayilir. Istemci bu kaydi
            // gonderemezse sessizce tekrar deniyor; kati bir kural yuzunden
            // 422'ye takilan bir istek kuyrukta sonsuza kadar donerdi.
            'offline' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id.required' => 'Oturum kaydı id değeri gerekli.',
            'id.uuid' => 'Oturum kaydı id değeri UUID olmalı.',
            'started_at.required' => 'Giriş zamanı gerekli.',
            'started_at.date' => 'Giriş zamanı ISO 8601 formatında olmalı.',
            'offline.boolean' => 'Çevrimdışı alanı doğru/yanlış olmalı.',
        ];
    }
}
