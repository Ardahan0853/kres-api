<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Sinifta cekilen fotograf.
 *
 * Dosyanin kendisi diskte durur, bu satir yalnizca ona isaret eder. Yukleme
 * ve kesinlestirme ayri adimlar oldugu icin satir ancak dosya yuklendikten
 * sonra olusur; kesinlestirilmemis dosyalari photos:prune temizler.
 *
 * institution_id bilerek fillable degildir; BelongsToInstitution trait'i onu
 * giris yapmis kullanicidan yazar.
 */
#[Fillable(['id', 'classroom_id', 'user_id', 'storage_key', 'byte_size', 'taken_at'])]
class Photo extends Model
{
    use BelongsToInstitution, HasUuids;

    /** Istemci ~400 KB'a dusuruyor; bu ust sinir genis birakilmis bir emniyet kemeri. */
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const CONTENT_TYPE = 'image/jpeg';

    /** Imzali yukleme adresinin omru. */
    public const UPLOAD_URL_MINUTES = 10;

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'byte_size' => 'integer',
        ];
    }

    /**
     * Diskteki yol. Istemcinin urettigi id'den turetilir, boylece ayni
     * fotograf icin adres kac kez istenirse istensin ayni anahtar doner.
     */
    public static function storageKeyFor(string $institutionId, string $photoId): string
    {
        return "photos/{$institutionId}/{$photoId}.jpg";
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

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class);
    }
}
