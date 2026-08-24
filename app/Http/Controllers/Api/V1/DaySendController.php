<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDaySendRequest;
use App\Http\Resources\DaySendResource;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\DaySend;
use App\Models\Photo;
use App\Support\ParentLinks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sinifin gununu veliye gonderme.
 *
 * Bu adim yalnizca gonderimin OLDUGUNU ve o andaki ozeti kaydeder; veliye
 * gercek bildirim gitmesi ayri bir adimdir.
 *
 * Gonderim cevrimdisiyken de kuyruga girdigi icin ayni istek tekrar tekrar
 * gelebilir. Iki ayri tekrar durumu vardir ve IKISI DE 200 doner:
 *   - ayni id            -> kuyruk aynen tekrar gonderdi
 *   - ayni gun, yeni id  -> ogretmen ikinci kez bastu
 * Ikincisine 409 DONMEYIZ: istemci 409'u kalici ret sayip ogretmene
 * "gonderilemedi" der, halbuki gun gitmistir. Yanlis bilgi vermektense
 * mevcut kaydi donmek dogrudur.
 *
 * YENIDEN GONDERIM bu iki tekrardan ayrilir ve yalnizca govdede `resend: true`
 * varken olur. Cikarim YAPILMAZ: "yeni id geldiyse ogretmen tekrar basmistir"
 * deseydik, yanit ag uzerinde kaybolup kuyruk yeni bir id ile tekrar
 * denediginde veli ikinci SMS'i alirdi. Acik bayrak, kuyrugun tekrari ile
 * ogretmenin ikinci NIYETINI ayiran tek guvenilir isarettir.
 *
 * Yeniden gonderimin bedeli bilinerek kabul edildi: baglantilar yeniden
 * uretilir ve bir kapsam icin tek canli baglanti oldugundan velinin elindeki
 * ESKI ADRES OLUR. Ham token saklanmadigi icin "eskisini tekrar gonder"
 * secenegi yok (bkz. MagicLink).
 */
class DaySendController extends Controller
{
    public function __construct(private ParentLinks $links) {}

    public function store(StoreDaySendRequest $request, string $classroomId): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Route model binding BILEREK kullanilmiyor: bulunamayan sinif icin
        // 404 uretirdi ve istemci 404'u "uc henuz yok" diye yorumlayip
        // gonderimi kuyrukta bekletirdi.
        $classroom = Classroom::find($classroomId);

        if ($classroom === null) {
            return $this->refuse('Sınıf bulunamadı.', 'classroom', 'classroom_not_found');
        }

        $assigned = $user->classrooms()->whereKey($classroom->getKey())->exists();

        if (! $user->isAdmin() && ! $assigned) {
            abort(403, 'Bu sınıfa atanmış değilsiniz.');
        }

        // 1. AYNI id daha once gorulduyse kuyruk aynen tekrar gondermistir.
        // Bildirim uretilmez; yeniden gonderim istense bile, cunku bu istek
        // yeni bir niyet degil eski bir istegin kopyasidir.
        $sameId = DaySend::find($data['id']);

        if ($sameId !== null) {
            return $this->resourceResponse($sameId, 200);
        }

        // 2. Gunun en son gonderimi. whereDate: `day` bir tarih kolonu ama
        // surucuye gore saat bilgisiyle birlikte saklanabiliyor; duz esitlik
        // SQLite'ta tutmuyor.
        $latest = DaySend::query()
            ->where('classroom_id', $classroom->getKey())
            ->whereDate('day', $data['day'])
            ->orderByDesc('attempt')
            ->first();

        // 3. Gun gonderilmis ve acikca yeniden gonderim ISTENMEMISSE eski
        // davranis aynen surer: mevcut kayit 200 ile doner, bildirim cikmaz.
        if ($latest !== null && ! ($data['resend'] ?? false)) {
            return $this->resourceResponse($latest, 200);
        }

        // Bayrak gelse bile gun hic gonderilmemisse bu ILK gonderimdir.
        $resend = $latest !== null;

        try {
            $daySend = DaySend::create([
                'id' => $data['id'],
                'classroom_id' => $classroom->getKey(),
                'user_id' => $user->getKey(),
                'day' => $data['day'],
                // Sirayi sunucu hesaplar; tekil kisit es zamanli iki istegin
                // ikisinin de bildirim uretmesini engeller.
                'attempt' => ($latest?->attempt ?? 0) + 1,
                'resend' => $resend,
                // Butona basildigi an: cihaz damgasi, sunucu saati degil.
                'requested_at' => $data['requested_at'],
                // Sunucunun gonderimi isledigi an; requested_at ile karistirilmaz.
                'sent_at' => now(),
                'child_count' => $this->childCount($classroom),
                'parent_count' => $this->parentCount($classroom),
                // Fotograflar AYRI kuyrukta oldugu icin hala yukleniyor
                // olabilir; o an elimizde ne varsa o sayilir.
                'photo_count' => $this->photoCount($classroom, $data['day']),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Iki ayri carpisma buraya duser: ayni id es zamanli iki istekle
            // geldi, ya da ayni gunun ayni sirasi. Ikisinde de KAZANAN taraf
            // bildirimi uretmistir; biz uretmeyiz, yoksa veli iki SMS alir.
            $raced = DaySend::find($data['id'])
                ?? DaySend::query()
                    ->where('classroom_id', $classroom->getKey())
                    ->whereDate('day', $data['day'])
                    ->orderByDesc('attempt')
                    ->first();

            if ($raced === null) {
                // Id baska bir kuruma ait. Kaydi DONMEYIZ; donmek o kurumun
                // gonderim saatini sizdirirdi.
                return $this->refuse('Gönderim kaydedilemedi.', 'id', 'conflict');
            }

            return $this->resourceResponse($raced, 200);
        }

        // Baglanti yalnizca SATIR ACILAN yolda uretilir: ilk gonderimde ve
        // acikca istenen yeniden gonderimde. 200 donen yollarin hicbirinde
        // veliye ikinci bildirim gitmez.
        // Baglantilar API yanitinda DONULMEZ: ogretmenin telefonunda veli
        // linki tutmanin faydasi yok, sizma yuzeyi artar.
        $this->links->dispatchFor($classroom, $data['day']);

        return $this->resourceResponse($daySend, 201);
    }

    private function childCount(Classroom $classroom): int
    {
        return Child::query()->where('classroom_id', $classroom->getKey())->count();
    }

    /**
     * Bilgilendirilecek veli sayisi.
     *
     * Bir velinin sinifta birden fazla cocugu olabilir (kardesler) ve bir
     * cocugun birden fazla velisi olabilir. Bu yuzden cocuk sayisindan
     * turetilemez; DISTINCT veli sayilir.
     */
    private function parentCount(Classroom $classroom): int
    {
        return DB::table('child_parent')
            ->join('children', 'children.id', '=', 'child_parent.child_id')
            ->where('children.classroom_id', $classroom->getKey())
            ->distinct()
            ->count('child_parent.parent_id');
    }

    /**
     * O gune ait fotograf sayisi.
     *
     * `day` ogretmenin yerel takvim gunudur, taken_at ise mutlak bir andir.
     * Cihazin saat dilimi govdede gelmedigi icin gun penceresi uygulama saat
     * dilimine (UTC) gore alinir. Kres gunu (yaklasik 07:00-19:00 yerel) her
     * iki takvimde de ayni gune dustugu icin pratikte fark etmez; yalnizca
     * gece yarisina cok yakin cekilen bir fotograf komsu gune kayabilir.
     */
    private function photoCount(Classroom $classroom, string $day): int
    {
        $start = Carbon::parse($day, 'UTC')->startOfDay();

        return Photo::query()
            ->where('classroom_id', $classroom->getKey())
            ->whereBetween('taken_at', [$start, $start->copy()->addDay()])
            ->count();
    }

    private function resourceResponse(DaySend $daySend, int $status): JsonResponse
    {
        return (new DaySendResource($daySend))->response()->setStatusCode($status);
    }

    /** Laravel'in standart 422 govdesi, ustune makine okunur `reason`. */
    private function refuse(string $message, string $field, string $reason): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
            'reason' => $reason,
        ], 422);
    }
}
