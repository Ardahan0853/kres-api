<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecordBatchRequest;
use App\Http\Requests\StoreRecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gunluk kayitlarin yazildigi uc.
 *
 * Istemci cevrimdisi calisir ve kuyruktaki kaydi birden fazla kez
 * gonderebilir; kotu baglantida zaman asimina dusen bir istek aslinda
 * sunucuya ulasmis olabilir. Bu yuzden yazma idempotenttir: id istemciden
 * gelir ve ayni id ikinci kez geldiginde yeni satir acilmaz, mevcut kayit
 * donulur.
 *
 * Durum kodlari istemcinin kuyruk davranisini dogrudan belirledigi icin
 * bilerek secilmistir:
 *   201 yeni kayit          200 zaten vardi (tekrar gonderim)
 *   403 sinifa atanmamis    409 id baska kuruma ait
 *   422 govde hatali
 *
 * 404 ASLA donulmez: istemci 404'u "uc henuz yok" diye yorumlar ve kaydi
 * kuyrukta bekletir. Bilinmeyen sinif veya cocuk bu yuzden 422 uretir.
 */
class RecordController extends Controller
{
    public function store(StoreRecordRequest $request): JsonResponse
    {
        $outcome = $this->persist($request->user(), $request->validated());

        if ($outcome['record'] instanceof Record) {
            return (new RecordResource($outcome['record']))
                ->response()
                ->setStatusCode($outcome['status']);
        }

        if ($outcome['status'] === 422) {
            throw ValidationException::withMessages([
                $outcome['field'] => [$outcome['message']],
            ]);
        }

        abort($outcome['status'], $outcome['message']);
    }

    /**
     * Toplu gonderim. Bahcedeki ogretmen binaya girdiginde 40-50 kayit birden
     * gider; tek tek istek atmak yerine hepsi tek istekte gonderilir.
     *
     * Yanit her zaman 200'dur ve kayit basina sonuc icerir. Istemci HTTP
     * koduna degil, kendi id'sinin satirina bakmalidir.
     */
    public function batch(StoreRecordBatchRequest $request): JsonResponse
    {
        $user = $request->user();
        $results = [];

        foreach ($request->validated()['records'] as $index => $input) {
            // Nesne olmayan eleman KENDI satirinda reddedilir. Zarf kuralina
            // baglasaydik bozuk tek bir kuyruk satiri, yanindaki 49 saglam
            // kaydin da sonsuza kadar reddedilmesine yol acardi.
            if (! is_array($input)) {
                $results[] = [
                    'id' => null,
                    'index' => $index,
                    'status' => 422,
                    'result' => 'invalid',
                    'message' => 'Her kayıt bir nesne olmalı.',
                ];

                continue;
            }

            $validator = Validator::make(
                $input,
                StoreRecordRequest::recordRules(),
                StoreRecordRequest::recordMessages()
            );

            if ($validator->fails()) {
                $results[] = [
                    // id dogrulanamamis olabilir; istemci satiri index ile de eslestirebilir.
                    'id' => is_string($input['id'] ?? null) ? $input['id'] : null,
                    'index' => $index,
                    'status' => 422,
                    'result' => 'invalid',
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            $outcome = $this->persist($user, $validator->validated());

            $row = [
                'id' => $input['id'],
                'index' => $index,
                'status' => $outcome['status'],
                'result' => $outcome['result'],
            ];

            if ($outcome['message'] !== null) {
                $row['message'] = $outcome['message'];
            }

            $results[] = $row;
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Ogretmenin yanlislikla girdigi kaydi siler.
     *
     * Silme YUMUSAKTIR: satir durur, deleted_at dolar. Boylece "kim ne zaman
     * sildi" izi kalir ve gecikmis bir POST kaydi diriltemez.
     *
     * Gun gonderilmis olsa bile silmeye izin verilir: veli sayfasi canli
     * okudugu icin duzeltme aninda yansir. Kilitleseydik yanlis kayit veliye
     * kalici olarak yanlis gorunurdu.
     */
    public function destroy(Request $request, string $recordId): Response
    {
        $user = $request->user();

        // Global scope kurum disini zaten eler; baska kurumun kaydi burada
        // "yok" gorunur ve 404 alir.
        $record = Record::find($recordId);

        if ($record === null) {
            // Zaten yok (ya da zaten silinmis). Istemci bunu basari sayiyor:
            // kuyruk ayni silme istegini tekrar gonderebilir.
            abort(404);
        }

        $assigned = $user->classrooms()->whereKey($record->classroom_id)->exists();

        if (! $user->isAdmin() && ! $assigned) {
            abort(403, 'Bu sınıfa atanmış değilsiniz.');
        }

        $record->delete();

        return response()->noContent();
    }

    /**
     * Tek kaydi dogrular, yetkilendirir ve yazar.
     *
     * @param  array<string, mixed>  $data
     * @return array{result: string, status: int, record: ?Record, message: ?string, field: ?string}
     */
    private function persist(User $user, array $data): array
    {
        // Global scope sorguyu kullanicinin kurumuyla sinirlar; baska kurumun
        // sinifi burada bulunamaz.
        $classroom = Classroom::find($data['classroom_id']);

        if ($classroom === null) {
            return $this->invalid('classroom_id', 'Sınıf bulunamadı.');
        }

        $assigned = $user->classrooms()->whereKey($classroom->getKey())->exists();

        if (! $user->isAdmin() && ! $assigned) {
            return $this->refused(403, 'forbidden', 'Bu sınıfa atanmış değilsiniz.');
        }

        $child = Child::query()
            ->where('classroom_id', $classroom->getKey())
            ->find($data['child_id']);

        if ($child === null) {
            return $this->invalid('child_id', 'Çocuk bu sınıfa ait değil.');
        }

        // Tekrar gonderim: kayit zaten yazilmis, yenisi acilmaz. Gelen govde
        // mevcut satirin uzerine yazilir; ogretmenin ikinci dokunusu secimi
        // degistirir (once "Yedi", sonra "Az yedi") ve uyku kaydi ayni id'ye
        // ended_at eklenerek kapanir. Yani kazanan son gonderimdir.
        $existing = Record::find($data['id']);

        if ($existing !== null) {
            return $this->applyUpdate($existing, $classroom, $child, $data);
        }

        // Silinmis kayit DIRILTILMEZ. Silme ile yazma iki ayri istektir ve
        // agda siralari bozulabilir: DELETE once varip 404 alabilir, ardindan
        // gecikmis POST gelirse kayit geri gelirdi. deleted_at doluysa istegi
        // basarili sayip satira dokunmuyoruz.
        $silinmis = Record::onlyTrashed()->find($data['id']);

        if ($silinmis !== null) {
            return $this->written($silinmis, 'deleted', 200);
        }

        // Ayni id baska kurumda duruyorsa kaydi DONMEYIZ; donmek o kurumun
        // verisini sizdirirdi. UUIDv7 carpismasi pratikte imkansiz oldugu icin
        // bu durum yalnizca kotu niyetli istekte olusur.
        if (Record::withoutGlobalScope('institution')->whereKey($data['id'])->exists()) {
            return $this->refused(409, 'conflict', 'Bu kayıt id değeri kullanımda.');
        }

        try {
            // institution_id govdeden degil, BelongsToInstitution trait'i
            // uzerinden giris yapmis kullanicidan yazilir.
            $record = Record::create([
                'id' => $data['id'],
                'classroom_id' => $classroom->getKey(),
                'child_id' => $child->getKey(),
                'user_id' => $user->getKey(),
                'type' => $data['type'],
                'value' => $data['value'] ?? null,
                // Sunucu saati DEGIL: olayin istemcide damgalanan gercek zamani.
                'recorded_at' => $data['recorded_at'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // Ayni kayit es zamanli iki istekle geldi. Kaybeden istek de
            // basarili sayilir ki kuyruk kaydi 'synced' isaretleyebilsin.
            $raced = Record::find($data['id']);

            return $raced !== null
                ? $this->applyUpdate($raced, $classroom, $child, $data)
                : $this->refused(409, 'conflict', 'Bu kayıt id değeri kullanımda.');
        }

        return $this->written($record, 'created', 201);
    }

    /**
     * Mevcut kaydi gelen govdeyle gunceller.
     *
     * Yalnizca value ve recorded_at degisir. Kaydin sinifi, cocugu ve turu
     * sabittir; bunlari degistiren bir govde istemcide olusmaz, geldiginde
     * sessizce tasimak yerine reddedilir.
     *
     * @param  array<string, mixed>  $data
     * @return array{result: string, status: int, record: ?Record, message: ?string, field: ?string}
     */
    private function applyUpdate(Record $existing, Classroom $classroom, Child $child, array $data): array
    {
        if ($existing->classroom_id !== $classroom->getKey()) {
            return $this->invalid('classroom_id', 'Kayıt başka bir sınıfa taşınamaz.');
        }

        if ($existing->child_id !== $child->getKey()) {
            return $this->invalid('child_id', 'Kayıt başka bir çocuğa taşınamaz.');
        }

        if ($existing->type !== $data['type']) {
            return $this->invalid('type', 'Kayıt türü değiştirilemez.');
        }

        // Gecikmis istek korumasi. Istemcide zaman asimina dusen bir istek agda
        // hala yolda olabilir; bu sirada ogretmen secimi degistirirse duzeltme
        // sunucuya once varip eski istek onun uzerine yazabilir. Kesin daha eski
        // damgali govdeyi yok sayiyoruz.
        //
        // Esitlik UYGULANIR: "geri al" akisi onceki degeri kendi eski damgasiyla
        // geri yazar ve o damga saklananla ayni olur; esitte reddetseydik geri
        // alma sunucuya hic islemezdi.
        //
        // Yanit yine 200; 4xx donersek kuyruk bu kaydi sonsuza kadar tekrar dener.
        if (Carbon::parse($data['recorded_at'])->lessThan($existing->recorded_at)) {
            return $this->written($existing, 'stale', 200);
        }

        // created_at ilk yazma aninda kalir, updated_at Eloquent tarafindan tazelenir.
        $existing->fill([
            'value' => $data['value'] ?? null,
            'recorded_at' => $data['recorded_at'],
        ])->save();

        return $this->written($existing, 'updated', 200);
    }

    /** @return array{result: string, status: int, record: ?Record, message: ?string, field: ?string} */
    private function written(Record $record, string $result, int $status): array
    {
        return [
            'result' => $result,
            'status' => $status,
            'record' => $record,
            'message' => null,
            'field' => null,
        ];
    }

    /** @return array{result: string, status: int, record: ?Record, message: ?string, field: ?string} */
    private function refused(int $status, string $result, string $message): array
    {
        return [
            'result' => $result,
            'status' => $status,
            'record' => null,
            'message' => $message,
            'field' => null,
        ];
    }

    /** @return array{result: string, status: int, record: ?Record, message: ?string, field: ?string} */
    private function invalid(string $field, string $message): array
    {
        return [
            'result' => 'invalid',
            'status' => 422,
            'record' => null,
            'message' => $message,
            'field' => $field,
        ];
    }
}
