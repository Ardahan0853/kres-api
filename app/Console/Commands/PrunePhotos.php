<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Sahipsiz fotograf dosyalarini siler.
 *
 * Yukleme ile kesinlestirme ayri adimlar oldugu icin dosya diske yazilip
 * POST /photos hic gelmeyebilir (ogretmen uygulamayi kapatti, kuyruk dustu).
 * Bu dosyalarin hicbir satiri yoktur ve kendiliklerinden gitmezler.
 *
 * Kuyruk saatlerce cevrimdisi bekleyebildigi icin varsayilan esik genis
 * tutulmustur; henuz kesinlestirilecek bir dosyayi silmek istemeyiz.
 */
class PrunePhotos extends Command
{
    protected $signature = 'photos:prune
                            {--hours=48 : Bu saatten eski sahipsiz dosyalar silinir}
                            {--dry-run : Hicbir sey silme, yalnizca ne silinecegini yaz}';

    protected $description = 'Kesinlestirilmemis fotograf dosyalarini diskten siler';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        if ($hours < 1) {
            $this->error('--hours en az 1 olmalı.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('filesystems.default'));
        $esik = now()->subHours($hours)->getTimestamp();

        $dosyalar = $disk->allFiles('photos');

        if ($dosyalar === []) {
            $this->info('photos klasöründe dosya yok.');

            return self::SUCCESS;
        }

        // Global scope kimlik dogrulanmamis konsol baglaminda devre disi kalir,
        // yani tum kurumlarin anahtarlari gorulur; temizlik zaten kuruma bagli degil.
        $kesinlesmis = Photo::query()->pluck('storage_key')->flip();

        $silinen = 0;
        $korunan = 0;

        foreach ($dosyalar as $dosya) {
            if ($kesinlesmis->has($dosya)) {
                $korunan++;

                continue;
            }

            if ($disk->lastModified($dosya) > $esik) {
                // Henuz genc: kesinlestirme yolda olabilir.
                $korunan++;

                continue;
            }

            $this->line(($dryRun ? 'silinecek: ' : 'silindi: ').$dosya);

            if (! $dryRun) {
                $disk->delete($dosya);
            }

            $silinen++;
        }

        $this->info(sprintf(
            '%d dosya %s, %d dosya korundu (eşik: %d saat).',
            $silinen,
            $dryRun ? 'silinecekti' : 'silindi',
            $korunan,
            $hours
        ));

        return self::SUCCESS;
    }
}
