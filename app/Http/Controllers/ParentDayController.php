<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\MagicLink;
use App\Models\Photo;
use App\Models\Record;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Velinin gordugu tek sayfa: bir cocugun bir gunu.
 *
 * Bu uc PUBLIC'tir, oturum yoktur; yetki yalnizca token'dan gelir. Bu yuzden
 * sayfada gosterilen her sey token'in kapsamiyla (tek veli, tek cocuk, tek
 * gun) sinirlidir. Sinifin diger cocuklarina ait hicbir veri -- isim, sayi,
 * fotograf -- bu sayfaya girmez.
 */
class ParentDayController extends Controller
{
    /** Fotograf baglantilarinin omru; sayfa acikken yetecek kadar. */
    private const PHOTO_URL_MINUTES = 15;

    public function show(string $token): Renderable|Response
    {
        $link = MagicLink::findValid($token);

        if ($link === null) {
            // Gecersiz ile suresi dolmus ayni cevabi verir; disaridan
            // token'in var olup olmadigi anlasilmasin.
            return response()->view('parent.invalid', [], 404);
        }

        // Global scope'lar kapali: veli oturum acmis bir kullanici degil,
        // kurum bilgisi token'in kendisinden geliyor.
        $child = Child::withoutGlobalScope('institution')
            ->with('classroom')
            ->findOrFail($link->child_id);

        $day = $link->day->toDateString();

        $link->forceFill(['last_used_at' => now()])->save();

        return view('parent.day', [
            'child' => $child,
            'classroom' => $child->classroom,
            'day' => $link->day,
            'ozet' => $this->summary($child, $day),
            'fotograflar' => $this->photos($child, $day),
        ]);
    }

    /**
     * O gunun ozeti.
     *
     * Turetilmis kolonlar (present, meal_amount, ...) sayesinde value JSON'ini
     * elle acmak gerekmiyor.
     *
     * @return array<string, mixed>
     */
    private function summary(Child $child, string $day): array
    {
        [$start, $end] = $this->dayWindow($day);

        // YALNIZCA kurum kapsami kaldirilir. withoutGlobalScopes() deseydik
        // yumusak silme kapsami da kalkar ve ogretmenin sildigi kayit veli
        // sayfasinda gorunmeye devam ederdi.
        $records = Record::withoutGlobalScope('institution')
            ->where('child_id', $child->getKey())
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();

        // Ayni tur icin birden fazla kayit varsa SONUNCUSU gecerlidir.
        // Ogretmen once "geldi" isaretleyip sonra "gelmedi"ye duzeltmis
        // olabilir; veliye en son secim gosterilmelidir.
        $attendance = $records->last(fn (Record $r) => $r->type === Record::TYPE_ATTENDANCE);
        $nap = $records->last(fn (Record $r) => $r->type === Record::TYPE_NAP);

        $uykuBasi = $this->parseTime($nap?->nap_started_at);
        $uykuSonu = $this->parseTime($nap?->nap_ended_at);

        $geldi = $attendance?->present;
        $kahvalti = $records->last(fn (Record $r) => $r->meal_kind === 'breakfast')?->meal_amount;
        $ogle = $records->last(fn (Record $r) => $r->meal_kind === 'lunch')?->meal_amount;
        // "snack" = ikindi.
        $ikindi = $records->last(fn (Record $r) => $r->meal_kind === 'snack')?->meal_amount;
        $tuvalet = $records->where('toilet_kind', 'toilet')->count();
        $bez = $records->where('toilet_kind', 'diaper')->count();

        // Cocuk gelmediyse "Kahvalti: Isaretlenmemis" gibi satirlar anlamsiz
        // gurultudur; verisi olmayanlari gizleriz. Verisi OLAN satir gizlenmez:
        // celiskili de olsa gercek bir kaydi veliden saklamak daha kotudur.
        $gelmedi = $geldi === false;

        return [
            'bos' => $records->isEmpty(),
            'geldi' => $geldi,
            'ogun_goster' => ! $gelmedi || $kahvalti !== null || $ogle !== null || $ikindi !== null,
            'uyku_goster' => ! $gelmedi || $uykuBasi !== null,
            'tuvalet_goster' => ! $gelmedi || $tuvalet > 0 || $bez > 0,
            'geldi_saat' => $this->local($attendance?->recorded_at),
            'kahvalti' => $kahvalti,
            'ogle' => $ogle,
            'ikindi' => $ikindi,
            'uyku_basi' => $this->local($uykuBasi),
            'uyku_sonu' => $this->local($uykuSonu),
            // Carbon ondalik dondurdugu icin acikca yuvarlanir; "0.0625 dk" olmasin.
            'uyku_dakika' => $uykuBasi && $uykuSonu
                ? (int) round($uykuBasi->diffInMinutes($uykuSonu))
                : null,
            'tuvalet' => $tuvalet,
            'bez' => $bez,
        ];
    }

    /**
     * Yalnizca bu cocugun etiketlendigi ve izni olan fotograflar.
     *
     * Izin yazma aninda da kontrol ediliyor, burada tekrar bakilmasi bilincli:
     * izin sonradan geri alinmissa eski fotograf da gorunmemeli.
     *
     * @return list<array{url: string, taken_at: Carbon}>
     */
    private function photos(Child $child, string $day): array
    {
        if (! $child->hasPhotoConsent()) {
            return [];
        }

        [$start, $end] = $this->dayWindow($day);

        $disk = Storage::disk(config('filesystems.default'));

        return Photo::withoutGlobalScope('institution')
            ->whereHas('children', fn ($q) => $q->where('children.id', $child->getKey()))
            ->whereBetween('taken_at', [$start, $end])
            ->orderBy('taken_at')
            ->get()
            ->map(fn (Photo $photo) => [
                // Imzali adres. Yol adresin icinde gecer ama imzasiz istek
                // reddedilir ve imza kisa surede gecersizlesir; yani adresi
                // kopyalayan biri sure dolunca dosyaya ulasamaz.
                'url' => $disk->temporaryUrl($photo->storage_key, now()->addMinutes(self::PHOTO_URL_MINUTES)),
                'taken_at' => $this->local($photo->taken_at),
            ])
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dayWindow(string $day): array
    {
        $start = Carbon::parse($day, 'UTC')->startOfDay();

        return [$start, $start->copy()->addDay()];
    }

    private function parseTime(?string $iso): ?Carbon
    {
        return $iso === null ? null : Carbon::parse($iso);
    }

    /**
     * Saatleri velinin gordugu dilime cevirir.
     *
     * Damgalar UTC saklaniyor; app.timezone da UTC oldugu icin cevrilmezse
     * veli 23:47'de girilen kaydi 20:47 olarak gorur.
     */
    private function local(?Carbon $at): ?Carbon
    {
        return $at?->timezone(config('kres.display_timezone'));
    }
}
