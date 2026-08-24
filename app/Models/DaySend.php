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
 *
 * Bir gunun BIRDEN FAZLA satiri olabilir: ilk gonderim ve ardindan acikca
 * istenen yeniden gonderimler. Sayilar (cocuk/fotograf/veli) her satirda O
 * ANIN degeridir, guncellenmez -- gonderim gecmisi boylece okunabilir kalir.
 */
#[Fillable(['id', 'classroom_id', 'user_id', 'day', 'attempt', 'resend', 'requested_at', 'sent_at', 'child_count', 'photo_count', 'parent_count'])]
class DaySend extends Model
{
    use BelongsToInstitution, HasUuids;

    protected function casts(): array
    {
        return [
            // Takvim tarihi; saat/dilim tasimaz.
            'day' => 'date',
            'attempt' => 'integer',
            'resend' => 'boolean',
            'requested_at' => 'datetime',
            'sent_at' => 'datetime',
            'child_count' => 'integer',
            'photo_count' => 'integer',
            'parent_count' => 'integer',
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
