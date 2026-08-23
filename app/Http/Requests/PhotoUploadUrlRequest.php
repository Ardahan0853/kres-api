<?php

namespace App\Http\Requests;

use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhotoUploadUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Fotografin id'sini istemci uretir; anahtar bundan turetilir.
            'id' => ['required', 'uuid'],
            'classroom_id' => ['required', 'uuid'],
            'content_type' => ['required', 'string', Rule::in([Photo::CONTENT_TYPE])],
            'byte_size' => ['required', 'integer', 'min:1', 'max:'.Photo::MAX_BYTES],
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
            'content_type.required' => 'Dosya türü gerekli.',
            'content_type.in' => 'Yalnızca JPEG fotoğraf yüklenebilir.',
            'byte_size.required' => 'Dosya boyutu gerekli.',
            'byte_size.integer' => 'Dosya boyutu sayı olmalı.',
            'byte_size.min' => 'Dosya boş olamaz.',
            'byte_size.max' => 'Fotoğraf en fazla 2 MB olabilir.',
        ];
    }
}
