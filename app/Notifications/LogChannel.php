<?php

namespace App\Notifications;

use App\Models\Child;
use App\Models\Guardian;
use Illuminate\Support\Facades\Log;

/**
 * Kuru calisma kanali: SMS GONDERMEZ, yalnizca log'a yazar.
 *
 * Telefon maskelenir; log dosyasi bir gun paylasilirsa velinin tam numarasi
 * disari cikmasin. Baglanti tam yazilir, kullanicinin tarayicida acabilmesi
 * icin kuru calismada tek yol bu.
 */
class LogChannel implements ParentLinkChannel
{
    public function send(Guardian $guardian, Child $child, string $day, string $url): void
    {
        Log::info('Veli baglantisi (kuru calisma, SMS gonderilmedi)', [
            'veli' => $guardian->name,
            'telefon' => $guardian->maskedPhone(),
            'cocuk' => $child->first_name.' '.$child->last_name,
            'gun' => $day,
            'baglanti' => $url,
        ]);
    }
}
