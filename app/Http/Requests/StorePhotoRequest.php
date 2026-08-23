<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePhotoRequest extends FormRequest
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
            'classroom_id' => ['required', 'uuid'],
            'storage_key' => ['required', 'string', 'max:512'],
            'taken_at' => ['required', 'date'],
            // Fotografta etiketlenen cocuklar. Bos dizi de gecerlidir:
            // ogretmen kimseyi etiketlemeden sinif fotografi cekebilir.
            'child_ids' => ['present', 'array'],
            'child_ids.*' => ['uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id.required' => 'Fotoğraf id değeri gerekli.',
            'id.uuid' => 'Fotoğraf id değeri UUID olmalı.',
            'classroom_id.required' => 'Sınıf id değeri gerekli.',
            'classroom_id.uuid' => 'Sınıf id değeri UUID olmalı.',
            'storage_key.required' => 'Dosya anahtarı gerekli.',
            'taken_at.required' => 'Çekim zamanı gerekli.',
            'taken_at.date' => 'Çekim zamanı ISO 8601 formatında olmalı.',
            'child_ids.present' => 'Etiketlenen çocuk listesi gerekli.',
            'child_ids.array' => 'Etiketlenen çocuk listesi dizi olmalı.',
            'child_ids.*.uuid' => 'Çocuk id değeri UUID olmalı.',
        ];
    }
}
