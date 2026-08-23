<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Photo */
class PhotoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classroom_id' => $this->classroom_id,
            'storage_key' => $this->storage_key,
            'byte_size' => $this->byte_size,
            // Fotografin cekildigi an (istemcinin damgaladigi deger)
            'taken_at' => $this->taken_at?->toIso8601String(),
            'child_ids' => $this->children->pluck('id')->all(),
            // Sunucunun kaydi teslim aldigi an
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
