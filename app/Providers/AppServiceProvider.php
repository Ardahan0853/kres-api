<?php

namespace App\Providers;

use App\Notifications\ParentLinkChannel;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Veli baglantisi kanali config'ten secilir; saglayici geldiginde
        // yeni bir sinif yazip config'i degistirmek yeterli olsun diye.
        $this->app->bind(ParentLinkChannel::class, function () {
            $secilen = config('kres.parent_link_channel');
            $sinif = config("kres.parent_link_channels.{$secilen}");

            if ($sinif === null) {
                throw new InvalidArgumentException("Tanimsiz veli baglanti kanali: {$secilen}");
            }

            return $this->app->make($sinif);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `artisan serve` alt surece yalnizca bir izin listesindeki ortam
        // degiskenlerini gecirir ve TEMP/TMP o listede yoktur. Windows'ta PHP
        // bu ikisi olmadan yazilabilir bir gecici dizin bulamaz; sonucta
        // gecici dosyaya alinmasi gereken her istek govdesi (~8 KB ustu)
        // "Unable to create temporary file" ile 500 dondurur. Fotograf
        // yukleme bu yuzden telefondan hic calismiyordu.
        //
        // Yalnizca yerel gelistirmeyi etkiler; uretimde `serve` kullanilmaz.
        ServeCommand::$passthroughVariables[] = 'TEMP';
        ServeCommand::$passthroughVariables[] = 'TMP';
    }
}
