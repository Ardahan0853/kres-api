<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin \App\Models\Classroom */
class ClassroomResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'children_count' => (int) $this->children_count,
            // Bilgilendirilecek DISTINCT veli sayisi (kardesler tek sayilir).
            'parent_count' => (int) $this->parent_count,
            // Bugunun gunu gonderilmisse damgasi, gonderilmemisse null.
            'day_sent_at' => $this->day_sent_at
                ? Carbon::parse($this->day_sent_at)->toIso8601String()
                : null,
        ];
    }
}
