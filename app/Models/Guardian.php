<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cocugun velisi.
 *
 * Sinif adi `Guardian`, tablo adi `parents`. PHP'de `Parent` ayrilmis bir
 * kelimedir ve sinif adi olarak kullanilamaz; tablo adini sozlesmeye sadik
 * birakip yalnizca sinif adini degistirdik.
 *
 * Bir cocugun birden fazla velisi, bir velinin birden fazla cocugu olabilir.
 * Bu yuzden veli sayisi cocuk sayisindan turetilemez, DISTINCT sayilir.
 */
#[Fillable(['name', 'phone', 'phone_raw'])]
#[Hidden(['phone', 'phone_raw'])]
class Guardian extends Model
{
    use BelongsToInstitution, HasUuids;

    protected $table = 'parents';

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_parent', 'parent_id', 'child_id')
            ->withPivot('relation');
    }

    /**
     * Telefonu E.164'e cevirir: 05525700853 -> +905525700853
     *
     * Cozulemeyen girdi icin null doner; cagiran taraf uydurmak yerine
     * reddetmelidir.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $trimmed = trim($raw);
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        // Zaten +90... yazilmissa basindaki + korunur.
        if (str_starts_with($trimmed, '+')) {
            return strlen($digits) >= 10 ? '+'.$digits : null;
        }

        return match (true) {
            // 05525700853
            strlen($digits) === 11 && str_starts_with($digits, '0') => '+90'.substr($digits, 1),
            // 5525700853
            strlen($digits) === 10 => '+90'.$digits,
            // 905525700853
            strlen($digits) === 12 && str_starts_with($digits, '90') => '+'.$digits,
            default => null,
        };
    }

    /** Log ve ekran icin: +905525700853 -> +90552*****53 */
    public function maskedPhone(): string
    {
        $p = $this->phone;

        if (strlen($p) < 8) {
            return str_repeat('*', max(0, strlen($p)));
        }

        return substr($p, 0, 6).str_repeat('*', strlen($p) - 8).substr($p, -2);
    }
}
