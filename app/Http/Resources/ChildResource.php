<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Child */
class ChildResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            // ISO 8601 takvim tarihi
            'birth_date' => $this->birth_date?->toDateString(),
            // granted | denied | pending. Yalnizca 'granted' olan cocuk
            // fotografta etiketlenebilir.
            'photo_consent' => $this->photo_consent,
        ];
    }
}
