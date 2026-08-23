<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir sinifin bir gununun veliye gonderilmesi.
 *
 * Su an yalnizca gonderimin OLDUGUNU ve o andaki ozeti kaydeder; veliye
 * gercek bildirim gitmesi ayri bir adimdir.
 */
#[Fillable(['id', 'classroom_id', 'user_id', 'day', 'requested_at', 'sent_at', 'child_count', 'photo_count'])]
class DaySend extends Model
{
    use BelongsToInstitution, HasUuids;

    protected function casts(): array
    {
        return [
            // Takvim tarihi; saat/dilim tasimaz.
            'day' => 'date',
            'requested_at' => 'datetime',
            'sent_at' => 'datetime',
            'child_count' => 'integer',
            'photo_count' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
