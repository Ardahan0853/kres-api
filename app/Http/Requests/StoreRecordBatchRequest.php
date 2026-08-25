<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordBatchRequest extends FormRequest
{
    /** Tek istekte kabul edilen en fazla kayit sayisi. */
    public const MAX_RECORDS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Yalnizca zarfi dogrular. Kayit basina sekil dogrulamasi bilerek burada
     * yapilmaz: tek bozuk kayit yuzunden 422 donersek istemcinin kuyrugundaki
     * diger 49 saglam kayit da birlikte reddedilir ve sonsuza kadar tekrar
     * denenir. Bunun yerine her kayit kendi sonucunu alir.
     *
     * Bu yuzden `records.*` uzerinde de kural YOKTUR. Once 'array' kurali
     * vardi ve nesne olmayan tek bir eleman tum zarfi 422 yapiyordu -- yani
     * yukaridaki gerekcenin tam olarak engellemek istedigi sey. Nesne olmayan
     * eleman artik kendi satirinda `invalid` doner.
     *
     * Zarf duzeyinde kalan tek sinir kayit SAYISIDIR: 200 ustu bir listeyi
     * kayit basina sonuca cevirmenin anlami yok, istemcinin listeyi bolmesi
     * gerekir. Bu 422 tekrar denenerek DEGIL, bolunerek gecilir.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:'.self::MAX_RECORDS],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'records.required' => 'Gönderilecek kayıt listesi gerekli.',
            'records.array' => 'Kayıt listesi dizi olmalı.',
            'records.min' => 'En az bir kayıt gönderilmeli.',
            'records.max' => 'Tek istekte en fazla '.self::MAX_RECORDS.' kayıt gönderilebilir.',
        ];
    }
}
