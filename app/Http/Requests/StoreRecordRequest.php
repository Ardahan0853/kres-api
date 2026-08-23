<?php

namespace App\Http\Requests;

use App\Models\Record;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tek bir kaydin sekil dogrulamasi. Toplu uc de ayni kurallari
     * kayit basina uygular; bu yuzden static olarak paylasilir.
     *
     * @return array<string, mixed>
     */
    public static function recordRules(): array
    {
        return [
            // Istemci uretir (UUIDv7). Idempotentlik buna dayanir.
            'id' => ['required', 'uuid'],
            'classroom_id' => ['required', 'uuid'],
            'child_id' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(Record::types())],
            // Serbest JSON. Kayit ekranlari yazilana kadar tip basina sema yok.
            'value' => ['nullable', 'array'],
            // Olayin gercek zamani. Sunucu saati kullanilmaz.
            'recorded_at' => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public static function recordMessages(): array
    {
        return [
            'id.required' => 'Kayıt id değeri gerekli.',
            'id.uuid' => 'Kayıt id değeri UUID olmalı.',
            'classroom_id.required' => 'Sınıf id değeri gerekli.',
            'classroom_id.uuid' => 'Sınıf id değeri UUID olmalı.',
            'child_id.required' => 'Çocuk id değeri gerekli.',
            'child_id.uuid' => 'Çocuk id değeri UUID olmalı.',
            'type.required' => 'Kayıt türü gerekli.',
            // Istemci bu mesaji ogretmene aynen gosteriyor; kuralsiz birakilirsa
            // Laravel'in Ingilizce varsayilani ekrana dusuyor.
            'type.string' => 'Geçersiz kayıt türü.',
            'type.in' => 'Geçersiz kayıt türü.',
            'value.array' => 'Kayıt içeriği nesne olmalı.',
            'recorded_at.required' => 'Kayıt zamanı gerekli.',
            'recorded_at.date' => 'Kayıt zamanı ISO 8601 formatında olmalı.',
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::recordRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::recordMessages();
    }
}
