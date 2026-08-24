<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SessionLog */
class SessionLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Girisin yapildigi an (istemcinin damgaladigi deger)
            'started_at' => $this->started_at?->toIso8601String(),
            'offline' => $this->offline,
            // Sunucunun kaydi teslim aldigi an
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
