<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Velinin sifresiz giris bagi.
 *
 * Kapsam: BIR veli + BIR cocuk + BIR gun. Uc bilesen de sabittir; link
 * gonderildigi gunu gosterir ve icerigi gun degistikce kaymaz.
 *
 * Ham token veritabaninda DURMAZ. Yalnizca sha256 ozeti saklanir ve dogrulama
 * gelen token'in ozeti hesaplanarak yapilir. Veritabani sizarsa eldeki
 * ozetlerden calisan bir link uretilemez.
 *
 * Bir kapsam icin AYNI ANDA TEK canli baglanti bulunur: yeni baglanti
 * uretildiginde eskisi iptal edilir. Aksi halde komut her calistiginda bir
 * anahtar daha eklenir, hicbiri geri alinamaz ve log'a ya da iletilen bir
 * mesaja dusen her adres suresi bitene kadar canli kalirdi.
 *
 * Bunun bedeli: yeni baglanti uretmek, daha once paylasilmis adresi
 * gecersiz kilar. Ham token saklanmadigi icin "eskisini yeniden goster"
 * secenegi yok; saklasaydik veritabani sizintisina karsi korumayi
 * kaybederdik.
 */
#[Fillable([])]
#[Hidden(['token_hash'])]
class MagicLink extends Model
{
    use BelongsToInstitution, HasUuids;

    /** Baglantinin gecerlilik suresi. */
    public const LIFETIME_DAYS = 7;

    /** Token'in ham bayt uzunlugu; base64url'de 43 karaktere karsilik gelir. */
    private const TOKEN_BYTES = 32;

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Yeni bir baglanti uretir.
     *
     * Ham token YALNIZCA burada bilinir ve donulur; cagiran taraf onu
     * kullanmazsa bir daha elde edilemez.
     *
     * @return array{0: self, 1: string} [kayit, ham token]
     */
    public static function issue(Guardian $guardian, Child $child, string $day): array
    {
        // Ayni kapsam icin YALNIZCA BIR canli baglanti kalir. Eskisi burada
        // iptal edilmezse komut her calistiginda bir anahtar daha eklenir ve
        // hicbiri geri alinamaz; log'a, ekran goruntusune ya da iletilen bir
        // mesaja dusen her adres suresi bitene kadar canli kalirdi.
        self::revokeFor($guardian, $child, $day);

        $token = self::newToken();

        $link = new self;
        $link->institution_id = $child->institution_id;
        $link->parent_id = $guardian->getKey();
        $link->child_id = $child->getKey();
        $link->day = $day;
        $link->token_hash = self::hash($token);
        $link->expires_at = now()->addDays(self::LIFETIME_DAYS);
        $link->save();

        return [$link, $token];
    }

    /**
     * Bir kapsam icin su an gecerli olan baglanti (varsa).
     *
     * Adresi geri veremez -- ham token saklanmiyor -- ama "canli bir baglanti
     * var mi, ne zaman uretilmis" sorusunu cevaplar. Uretim araclari bunu
     * kontrol edip velinin elindeki adresi kazara oldurmemek icin kullanir.
     */
    public static function liveFor(Guardian $guardian, Child $child, string $day): ?self
    {
        return self::withoutGlobalScopes()
            ->where('parent_id', $guardian->getKey())
            ->where('child_id', $child->getKey())
            ->whereDate('day', $day)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();
    }

    /**
     * Bir kapsam icin canli baglantilari iptal eder.
     *
     * @return int iptal edilen baglanti sayisi
     */
    public static function revokeFor(Guardian $guardian, Child $child, string $day): int
    {
        return self::withoutGlobalScopes()
            ->where('parent_id', $guardian->getKey())
            ->where('child_id', $child->getKey())
            ->whereDate('day', $day)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** URL'de guvenle tasinabilen rastgele token. */
    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Token'a karsilik gelen gecerli baglantiyi bulur.
     *
     * Kurum global scope'u BILEREK devre disi: veli oturum acmis bir kullanici
     * degildir, hangi kuruma ait oldugu token'in kendisinden cikar.
     */
    public static function findValid(string $token): ?self
    {
        return self::withoutGlobalScopes()
            ->where('token_hash', self::hash($token))
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
