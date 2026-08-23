<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
