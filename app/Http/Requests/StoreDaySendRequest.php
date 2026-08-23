<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDaySendRequest extends FormRequest
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
            // Ogretmenin yerel takvim gunu. UTC'ye cevrilmez.
            'day' => ['required', 'date_format:Y-m-d'],
            // Butona basildigi an.
            'requested_at' => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id.required' => 'Gönderim id değeri gerekli.',
            'id.uuid' => 'Gönderim id değeri UUID olmalı.',
            'day.required' => 'Gün gerekli.',
            'day.date_format' => 'Gün YYYY-AA-GG biçiminde olmalı.',
            'requested_at.required' => 'Gönderim zamanı gerekli.',
            'requested_at.date' => 'Gönderim zamanı ISO 8601 formatında olmalı.',
        ];
    }
}
