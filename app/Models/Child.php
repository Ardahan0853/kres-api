<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['institution_id', 'classroom_id', 'first_name', 'last_name', 'birth_date', 'photo_consent'])]
class Child extends Model
{
    use BelongsToInstitution, HasUuids;

    /** Velinin fotograf izni verdigi cocuk. */
    public const CONSENT_GRANTED = 'granted';

    /** Veli acikca izin vermedi. */
    public const CONSENT_DENIED = 'denied';

    /** Izin formu henuz donmedi. Guvenli varsayilan budur. */
    public const CONSENT_PENDING = 'pending';

    /** Tekil "child" cogul "children" oldugu icin tablo adi acikca verilir. */
    protected $table = 'children';

    /**
     * Test verisi icin izin dagitimi.
     *
     * Her sinifta en az bir 'denied' ve bir 'pending' bulunmasi sart: izin
     * akisi ancak ucu de varken gercekten denenebilir. Seeder ve mevcut
     * satirlari dolduran migration ayni kurali kullanir.
     */
    public static function consentForIndex(int $index): string
    {
        return match (true) {
            $index === 0 => self::CONSENT_DENIED,
            $index === 1 => self::CONSENT_PENDING,
            $index % 7 === 0 => self::CONSENT_PENDING,
            $index % 5 === 0 => self::CONSENT_DENIED,
            default => self::CONSENT_GRANTED,
        };
    }

    public function hasPhotoConsent(): bool
    {
        return $this->photo_consent === self::CONSENT_GRANTED;
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
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

    /** Bir cocugun birden fazla velisi olabilir. */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'child_parent', 'child_id', 'parent_id')
            ->withPivot('relation');
    }
}
