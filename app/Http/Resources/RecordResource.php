<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Record */
class RecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom_id' => $this->classroom_id,
            'child_id' => $this->child_id,
            'type' => $this->type,
            'value' => $this->value,
            // Olayin gercek zamani (istemcinin damgaladigi deger)
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            // Sunucunun kaydi teslim aldigi an
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
