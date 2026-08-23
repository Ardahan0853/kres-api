<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PhotoUploadUrlRequest;
use App\Http\Requests\StorePhotoRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Fotograf yukleme akisi.
 *
 * Uc adim vardir ve ucu de birbirinden bagimsiz tekrar denenebilir:
 *   1. POST photos/upload-url  -> kisa omurlu imzali adres
 *   2. PUT  <imzali adres>     -> dosyanin kendisi (Laravel'in storage rotasi)
 *   3. POST photos             -> kaydin kesinlestirilmesi
 *
 * Ikinci adimi Laravel'in kendi rotasi karsilar ve YALNIZCA IMZAYI dogrular;
 * icerik turune ve boyuta bakmaz. Bu yuzden gercek denetim ucuncu adimdadir:
 * dosyanin diskteki gercek boyutuna ve JPEG olup olmadigina orada bakariz.
 */
class PhotoController extends Controller
{
    /**
     * 422 govdesindeki makine okunur gerekce.
     *
     * Istemci 422'yi kalici hata sayip fotografi birakiyor. Ama asagidaki uc
     * durum (dosya yok / bos / JPEG degil) cogunlukla YARIM KALMIS bir PUT'tan
     * cikar ve bahce wifi'sinde en sik gorulen hata budur. Dogru kurtarma
     * vazgecmek degil, dosyayi bastan yuklemektir; bu yuzden onlari tek bir
     * 'upload_incomplete' altinda toplayip digerlerinden ayiriyoruz.
     *
     * Istemci Turkce mesaji ayristirmak zorunda kalmasin diye deger sabittir.
     */
    private const REASON_UPLOAD_INCOMPLETE = 'upload_incomplete';

    private const REASON_TOO_LARGE = 'too_large';

    private const REASON_KEY_MISMATCH = 'key_mismatch';

    private const REASON_CONSENT_BLOCKED = 'consent_blocked';

    private const REASON_NOT_IN_CLASSROOM = 'not_in_classroom';

    public function uploadUrl(PhotoUploadUrlRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($refusal = $this->guardClassroom($user, $data['classroom_id'])) {
            return $refusal;
        }

        $key = Photo::storageKeyFor($user->institution_id, $data['id']);
        $expiresAt = now()->addMinutes(Photo::UPLOAD_URL_MINUTES);

        $signed = Storage::disk($this->disk())->temporaryUploadUrl($key, $expiresAt);

        return response()->json([
            'upload_url' => $signed['url'],
            'method' => 'PUT',
            'headers' => array_merge($signed['headers'] ?? [], [
                'Content-Type' => Photo::CONTENT_TYPE,
            ]),
            'storage_key' => $key,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function store(StorePhotoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($refusal = $this->guardClassroom($user, $data['classroom_id'])) {
            return $refusal;
        }

        // Tekrar kesinlestirme: yukleme kuyrugu ayni fotografi birden fazla
        // kez gonderebilir, yeni satir acilmaz.
        $existing = Photo::with('children')->find($data['id']);

        if ($existing !== null) {
            return $this->resourceResponse($existing, 200);
        }

        // Anahtar id'den turetildigi icin baskasinin dosyasini sahiplenmek
        // mumkun olmasin diye beklenen degerle karsilastirilir.
        $expectedKey = Photo::storageKeyFor($user->institution_id, $data['id']);

        if ($data['storage_key'] !== $expectedKey) {
            return $this->refuse(
                'storage_key',
                'Dosya anahtarı bu fotoğrafa ait değil.',
                self::REASON_KEY_MISMATCH
            );
        }

        if ($fileError = $this->validateUploadedFile($expectedKey)) {
            return $fileError;
        }

        if ($childError = $this->validateTaggedChildren($data['classroom_id'], $data['child_ids'])) {
            return $childError;
        }

        try {
            $photo = DB::transaction(function () use ($data, $user, $expectedKey) {
                $photo = Photo::create([
                    'id' => $data['id'],
                    'classroom_id' => $data['classroom_id'],
                    'user_id' => $user->getKey(),
                    'storage_key' => $expectedKey,
                    'byte_size' => Storage::disk($this->disk())->size($expectedKey),
                    // Sunucu saati DEGIL: fotografin cihazda damgalanan cekim ani.
                    'taken_at' => $data['taken_at'],
                ]);

                $photo->children()->sync($data['child_ids']);

                return $photo;
            });
        } catch (UniqueConstraintViolationException) {
            // Ayni fotograf es zamanli iki istekle kesinlestirildi.
            $raced = Photo::with('children')->find($data['id']);

            if ($raced === null) {
                throw ValidationException::withMessages([
                    'id' => ['Bu fotoğraf id değeri kullanımda.'],
                ]);
            }

            return $this->resourceResponse($raced, 200);
        }

        return $this->resourceResponse($photo->load('children'), 201);
    }

    /**
     * Sinif kullanicinin kurumunda mi ve ogretmen o sinifa atanmis mi?
     *
     * Bilinmeyen sinif 404 degil 422 uretir; istemci 404'u "uc henuz yok"
     * diye yorumlayip fotografi kuyrukta bekletirdi.
     */
    private function guardClassroom(User $user, string $classroomId): ?JsonResponse
    {
        $classroom = Classroom::find($classroomId);

        if ($classroom === null) {
            throw ValidationException::withMessages([
                'classroom_id' => ['Sınıf bulunamadı.'],
            ]);
        }

        $assigned = $user->classrooms()->whereKey($classroom->getKey())->exists();

        if (! $user->isAdmin() && ! $assigned) {
            abort(403, 'Bu sınıfa atanmış değilsiniz.');
        }

        return null;
    }

    /**
     * Diskteki dosyanin gercekten yuklendigini, boyutunu ve JPEG oldugunu
     * dogrular. Yukleme rotasi bunlarin hicbirine bakmadigi icin tek denetim
     * noktasi burasidir.
     */
    private function validateUploadedFile(string $key): ?JsonResponse
    {
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($key)) {
            return $this->refuse(
                'storage_key',
                'Fotoğraf sunucuya tam ulaşmadı. Yeniden yüklenecek.',
                self::REASON_UPLOAD_INCOMPLETE
            );
        }

        $size = $disk->size($key);

        if ($size <= 0) {
            return $this->refuse(
                'storage_key',
                'Fotoğraf sunucuya tam ulaşmadı. Yeniden yüklenecek.',
                self::REASON_UPLOAD_INCOMPLETE
            );
        }

        // Boyut kontrolu JPEG kontrolunden ONCE gelir: 2 MB'i asan dosya
        // kalicidir, yeniden yuklemek duzeltmez.
        if ($size > Photo::MAX_BYTES) {
            return $this->refuse(
                'storage_key',
                'Fotoğraf en fazla 2 MB olabilir.',
                self::REASON_TOO_LARGE
            );
        }

        if (! $this->looksLikeJpeg($disk->readStream($key))) {
            // Dosya JPEG olarak baslamiyor. Gercekten baska bir tur olabilir,
            // ama pratikte cok daha sik gorulen sebep yarim inen govdedir;
            // ikisini ayirt edemedigimiz icin yeniden yuklemeyi deniyoruz.
            return $this->refuse(
                'storage_key',
                'Fotoğraf sunucuya tam ulaşmadı. Yeniden yüklenecek.',
                self::REASON_UPLOAD_INCOMPLETE
            );
        }

        return null;
    }

    /** @param  resource|null|false  $stream */
    private function looksLikeJpeg($stream): bool
    {
        if (! is_resource($stream)) {
            return false;
        }

        $head = fread($stream, 3);
        fclose($stream);

        return $head === "\xFF\xD8\xFF";
    }

    /**
     * Etiketlenen cocuklar bu sinifa ait mi ve fotograf izinleri var mi?
     *
     * Istemci zaten izinsiz cocugu sectirmiyor, ama tek savunma hatti istemci
     * olmamali. Sorunlu id'ler yanitta acikca donulur ki istemci fotografi
     * "engellendi" isaretleyip ogretmene gosterebilsin.
     *
     * @param  list<string>  $childIds
     */
    private function validateTaggedChildren(string $classroomId, array $childIds): ?JsonResponse
    {
        if ($childIds === []) {
            return null;
        }

        $children = Child::query()
            ->where('classroom_id', $classroomId)
            ->whereIn('id', $childIds)
            ->get();

        $bulunmayan = array_values(array_diff($childIds, $children->pluck('id')->all()));

        if ($bulunmayan !== []) {
            return $this->refuse(
                'child_ids',
                'Etiketlenen çocuk bu sınıfa ait değil.',
                self::REASON_NOT_IN_CLASSROOM,
                ['blocked_child_ids' => $bulunmayan]
            );
        }

        $izinsiz = $children
            ->reject(fn (Child $child) => $child->hasPhotoConsent())
            ->pluck('id')
            ->all();

        if ($izinsiz !== []) {
            return $this->refuse(
                'child_ids',
                'Fotoğraf izni olmayan çocuk etiketlenemez.',
                self::REASON_CONSENT_BLOCKED,
                ['blocked_child_ids' => array_values($izinsiz)]
            );
        }

        return null;
    }

    /**
     * Laravel'in standart 422 govdesi, ustune makine okunur `reason`.
     *
     * Mevcut alanlar (message, errors, blocked_child_ids) aynen korunur;
     * `reason` yalnizca eklemedir.
     *
     * @param  array<string, mixed>  $extra
     */
    private function refuse(string $field, string $message, string $reason, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $message,
            'errors' => [$field => [$message]],
            'reason' => $reason,
        ], $extra), 422);
    }

    private function resourceResponse(Photo $photo, int $status): JsonResponse
    {
        return (new PhotoResource($photo))->response()->setStatusCode($status);
    }

    /** Dev'de yerel disk, uretimde s3. Istemci akisi ikisinde de aynidir. */
    private function disk(): string
    {
        return config('filesystems.default');
    }
}
