<?php

namespace App\Notifications;

use App\Models\Child;
use App\Models\Guardian;

/**
 * Veliye baglantiyi ulastiran kanal.
 *
 * Gonderim mantigi bu arayuzun arkasinda durur ki saglayici geldiginde
 * day-send koduna dokunmadan, yalnizca config degistirilerek takilabilsin.
 * Su an tek uygulama LogChannel'dir (kuru calisma, SMS gonderilmez).
 */
interface ParentLinkChannel
{
    public function send(Guardian $guardian, Child $child, string $day, string $url): void;
}
