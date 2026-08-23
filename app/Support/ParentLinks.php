<?php

namespace App\Support;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\MagicLink;
use App\Notifications\ParentLinkChannel;
use Illuminate\Support\Collection;

/**
 * Bir sinifin bir gunu icin veli baglantilarini uretir.
 *
 * Uretme ile gonderme bilerek ayrildi: `parent:links` komutu baglantilari
 * yalnizca uretip konsola basar, day-send ise ayrica kanaldan gonderir.
 */
class ParentLinks
{
    public function __construct(private ParentLinkChannel $channel) {}

    /**
     * Siniftaki her cocugun her velisi icin baglanti uretir.
     *
     * Ayni veli sinifta iki cocuga sahipse (kardesler) HER COCUK ICIN ayri
     * baglanti alir; sayfa tek cocuk icindir, tek link iki cocugu gosteremez.
     *
     * @return Collection<int, array{guardian: Guardian, child: Child, url: string}>
     */
    /**
     * Baglanti durumunu cikarir; gerekiyorsa uretir.
     *
     * `$rotate` false iken CANLI bir baglanti varsa YENISI URETILMEZ. Sebebi
     * somut: gun gonderildiginde veliye bir adres gitmis olabilir ve uretim
     * eskisini iptal ettigi icin, hata ayiklamak icin calistirilan bir komut
     * velinin elindeki adresi sessizce oldururdu.
     *
     * @return Collection<int, array{guardian: Guardian, child: Child, url: ?string, existing: ?MagicLink}>
     */
    public function planFor(Classroom $classroom, string $day, bool $rotate, ?string $baseUrl = null): Collection
    {
        $satirlar = collect();

        foreach ($this->childrenWithParents($classroom) as $child) {
            foreach ($child->parents as $guardian) {
                $canli = MagicLink::liveFor($guardian, $child, $day);

                if ($canli !== null && ! $rotate) {
                    $satirlar->push([
                        'guardian' => $guardian,
                        'child' => $child,
                        'url' => null,
                        'existing' => $canli,
                    ]);

                    continue;
                }

                [, $token] = MagicLink::issue($guardian, $child, $day);

                $satirlar->push([
                    'guardian' => $guardian,
                    'child' => $child,
                    'url' => $this->url($token, $baseUrl),
                    'existing' => $canli,
                ]);
            }
        }

        return $satirlar;
    }

    public function issueFor(Classroom $classroom, string $day, ?string $baseUrl = null): Collection
    {
        $links = collect();

        foreach ($this->childrenWithParents($classroom) as $child) {
            foreach ($child->parents as $guardian) {
                [, $token] = MagicLink::issue($guardian, $child, $day);

                $links->push([
                    'guardian' => $guardian,
                    'child' => $child,
                    'url' => $this->url($token, $baseUrl),
                ]);
            }
        }

        return $links;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Child> */
    private function childrenWithParents(Classroom $classroom)
    {
        return Child::withoutGlobalScope('institution')
            ->with('parents')
            ->where('classroom_id', $classroom->getKey())
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Baglantinin tam adresi.
     *
     * Konsolda istek host'u yoktur ve route() APP_URL'e duser; APP_URL de
     * genellikle localhost'tur. Telefonda localhost telefonun kendisi demek
     * oldugu icin komut `--url` ile taban adresi degistirebilir.
     */
    private function url(string $token, ?string $baseUrl): string
    {
        $path = route('parent.day', ['token' => $token], absolute: false);

        return $baseUrl === null
            ? url($path)
            : rtrim($baseUrl, '/').$path;
    }

    /**
     * Baglantilari uretip yapilandirilmis kanaldan gonderir.
     *
     * @return int gonderilen baglanti sayisi
     */
    public function dispatchFor(Classroom $classroom, string $day): int
    {
        $links = $this->issueFor($classroom, $day);

        foreach ($links as $link) {
            $this->channel->send($link['guardian'], $link['child'], $day, $link['url']);
        }

        return $links->count();
    }
}
