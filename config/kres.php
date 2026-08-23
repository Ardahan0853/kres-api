<?php

use App\Notifications\LogChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Gelistirme test velisi
    |--------------------------------------------------------------------------
    |
    | DevParentSeeder bu numarayla tek bir veli olusturur. Numara koda ve
    | depoya girmesin diye yalnizca .env'den okunur; bos birakilirsa veli
    | hic olusturulmaz.
    |
    */

    'dev_parent_phone' => env('DEV_PARENT_PHONE', ''),

    /*
    |--------------------------------------------------------------------------
    | Gosterim saat dilimi
    |--------------------------------------------------------------------------
    |
    | Damgalar UTC saklanir (app.timezone da UTC'dir). Veli sayfasindaki
    | saatler bu dilime cevrilerek gosterilir; aksi halde 23:47'de girilen
    | kayit veliye 20:47 olarak gorunur.
    |
    */

    'display_timezone' => env('KRES_DISPLAY_TIMEZONE', 'Europe/Istanbul'),

    /*
    |--------------------------------------------------------------------------
    | Veli baglantisi kanali
    |--------------------------------------------------------------------------
    |
    | Kuru calisma: 'log' SMS GONDERMEZ, baglantiyi yalnizca log'a yazar.
    | Saglayici geldiginde ParentLinkChannel'i uygulayan yeni bir sinif yazip
    | buraya eklemek yeterli; day-send koduna dokunulmaz.
    |
    */

    'parent_link_channel' => env('KRES_PARENT_LINK_CHANNEL', 'log'),

    'parent_link_channels' => [
        'log' => LogChannel::class,
    ],

];
