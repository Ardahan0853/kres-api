<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ogretmenin uygulamaya giris kaydi (denetim izi).
 *
 * DIKKAT: started_at ve offline ISTEMCININ BEYANIDIR. Cevrimdisi giris
 * sunucudan gecmedigi icin sunucunun dogrulayabilecegi bir sey yoktur;
 * bu tablo "ne oldugunun kaniti" degil, "cihazin ne bildirdigi"dir.
 * Kritik bir karara dayanak yapilmamalidir.
 */
#[Fillable(['id', 'user_id', 'started_at', 'offline'])]
class SessionLog extends Model
{
    use BelongsToInstitution, HasUuids;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'offline' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
