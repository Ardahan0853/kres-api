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
            // Acik yeniden gonderim istegi. Gelmezse false; ASLA "yeni id
            // geldi" diye cikarim yapilmaz -- kuyruk tekrarlari veliye
            // ikinci SMS olarak giderdi.
            //
            // `offline` gibi bilerek gevsek dogrulanir: kati bir kural
            // yuzunden 422'ye takilan gonderim istemcinin kuyrugunda
            // sonsuza kadar donerdi.
            'resend' => ['sometimes', 'boolean'],
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
