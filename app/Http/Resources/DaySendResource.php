<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DaySend */
class DaySendResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom_id' => $this->classroom_id,
            // Ogretmenin yerel takvim gunu (YYYY-MM-DD)
            'day' => $this->day?->toDateString(),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'child_count' => $this->child_count,
            'photo_count' => $this->photo_count,
            // Veli verisi bu semada HENUZ YOK; uydurma sayi donmemek icin null.
            'parent_count' => null,
        ];
    }
}
