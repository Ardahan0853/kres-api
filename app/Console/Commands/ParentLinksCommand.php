<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\MagicLink;
use App\Support\ParentLinks;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Veli baglantilarinin durumunu konsola basar.
 *
 * VARSAYILAN OLARAK URETMEZ. Uretim mevcut baglantiyi iptal ettigi icin,
 * hata ayiklamak icin calistirilan bir komut velinin elindeki adresi
 * sessizce oldururdu. Canli baglanti varsa yalnizca varligi bildirilir;
 * yenisi acikca --rotate ile istenir.
 */
class ParentLinksCommand extends Command
{
    protected $signature = 'parent:links
                            {--classroom= : Sinif id ya da adi}
                            {--day= : YYYY-AA-GG (varsayilan bugun)}
                            {--url= : Baglantilarin taban adresi, orn. http://192.168.1.2:8000}
                            {--rotate : Canli baglantiyi iptal edip yenisini uret}';

    protected $description = 'Bir sinifin bir gunu icin veli baglanti durumunu yazar';

    public function handle(ParentLinks $links): int
    {
        $arananSinif = (string) $this->option('classroom');

        if ($arananSinif === '') {
            $this->error('--classroom gerekli (sinif id ya da adi).');

            return self::FAILURE;
        }

        // Konsolda oturum yok, global scope devre disi kalir.
        // id kolonu uuid tipinde; UUID olmayan bir metni ona karsi
        // karsilastirmak PostgreSQL'de hata verir, o yuzden ayrilir.
        $classroom = Classroom::withoutGlobalScopes()
            ->when(
                Str::isUuid($arananSinif),
                fn ($q) => $q->whereKey($arananSinif),
                fn ($q) => $q->where('name', $arananSinif)
            )
            ->first();

        if ($classroom === null) {
            $this->error("Sinif bulunamadi: {$arananSinif}");

            return self::FAILURE;
        }

        $day = (string) ($this->option('day') ?: now()->toDateString());

        $base = (string) $this->option('url') ?: null;
        $rotate = (bool) $this->option('rotate');

        if ($rotate) {
            $this->warn('--rotate: mevcut baglantilar iptal edilecek. Veliye daha once gonderilmis bir adres varsa ARTIK ACILMAYACAK.');
            $this->newLine();
        }

        $satirlar = $links->planFor($classroom, $day, $rotate, $base);

        if ($satirlar->isEmpty()) {
            $this->warn("{$classroom->name} sinifinda veli kayitli degil.");

            return self::SUCCESS;
        }

        $this->info("{$classroom->name} · {$day} · {$satirlar->count()} veli");
        $this->newLine();

        foreach ($satirlar as $satir) {
            $this->line(sprintf(
                '%s (%s) -> %s %s',
                $satir['guardian']->name,
                $satir['guardian']->maskedPhone(),
                $satir['child']->first_name,
                $satir['child']->last_name
            ));

            if ($satir['url'] === null) {
                // Adres gosterilemez: ham token saklanmiyor, yalnizca ozeti.
                $this->line(sprintf(
                    '  <fg=yellow>canli baglanti var</> (uretim: %s) — adres gosterilemez, hash olarak saklaniyor',
                    $satir['existing']->created_at->timezone(config('kres.display_timezone'))->format('d.m.Y H:i')
                ));
            } else {
                $this->line('  '.$satir['url']);
            }

            $this->newLine();
        }

        $uretilenler = $satirlar->whereNotNull('url');

        if ($uretilenler->isEmpty()) {
            $this->comment('Yeni baglanti uretilmedi. Kuru calismada adresler day-send aninda log\'a yazilir.');
            $this->comment('Yenisini uretmek (ve eskisini iptal etmek) icin: --rotate');

            return self::SUCCESS;
        }

        $this->comment('Bu baglantilar '.MagicLink::LIFETIME_DAYS.' gun gecerlidir.');

        // Telefonda "localhost" telefonun kendisidir; boyle bir adres acilmaz.
        if ($base === null && preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)/', (string) $uretilenler->first()['url'])) {
            $this->newLine();
            $this->warn('Adres localhost; telefonda acilmaz. Bilgisayarin yerel IP adresiyle uretmek icin:');
            $this->warn('  php artisan parent:links --classroom='.$classroom->name.' --day='.$day.' --url=http://192.168.1.2:8000');
        }

        return self::SUCCESS;
    }
}
