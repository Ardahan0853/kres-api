<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private Institution $papatya;

    private Classroom $atanmis;

    private Classroom $atanmamis;

    private Child $izinli;

    private Child $izinsiz;

    private Child $bekleyen;

    private Child $digerSinifCocugu;

    private User $ayse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->papatya = Institution::create(['name' => 'Papatya Anaokulu']);

        $this->atanmis = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Papatyalar',
        ]);

        $this->atanmamis = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Menekşeler',
        ]);

        $this->izinli = $this->child($this->atanmis, 'Deniz', Child::CONSENT_GRANTED);
        $this->izinsiz = $this->child($this->atanmis, 'Ada', Child::CONSENT_DENIED);
        $this->bekleyen = $this->child($this->atanmis, 'Ege', Child::CONSENT_PENDING);
        $this->digerSinifCocugu = $this->child($this->atanmamis, 'Bora', Child::CONSENT_GRANTED);

        $this->ayse = User::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->ayse->classrooms()->attach($this->atanmis->id);
    }

    private function child(Classroom $classroom, string $firstName, string $consent): Child
    {
        return Child::create([
            'institution_id' => $classroom->institution_id,
            'classroom_id' => $classroom->id,
            'first_name' => $firstName,
            'last_name' => 'Yılmaz',
            'birth_date' => '2022-04-01',
            'photo_consent' => $consent,
        ]);
    }

    private function jpeg(int $bytes = 200): string
    {
        return "\xFF\xD8\xFF".str_repeat('a', max(0, $bytes - 3));
    }

    /** Yuklemeyi taklit eder: dosyayi beklenen anahtara koyar. */
    private function upload(string $photoId, ?string $content = null): string
    {
        $key = Photo::storageKeyFor($this->papatya->id, $photoId);
        Storage::disk('local')->put($key, $content ?? $this->jpeg());

        return $key;
    }

    /** @return array<string, mixed> */
    private function payload(string $photoId, array $overrides = []): array
    {
        return array_merge([
            'id' => $photoId,
            'classroom_id' => $this->atanmis->id,
            'storage_key' => Photo::storageKeyFor($this->papatya->id, $photoId),
            'taken_at' => '2026-08-23T10:30:00.000Z',
            'child_ids' => [$this->izinli->id],
        ], $overrides);
    }

    // --- B: upload-url ---

    public function test_imzali_yukleme_adresi_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();

        $response = $this->postJson('/api/v1/photos/upload-url', [
            'id' => $id,
            'classroom_id' => $this->atanmis->id,
            'content_type' => 'image/jpeg',
            'byte_size' => 384000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('method', 'PUT')
            ->assertJsonPath('headers.Content-Type', 'image/jpeg')
            ->assertJsonPath('storage_key', Photo::storageKeyFor($this->papatya->id, $id))
            ->assertJsonStructure(['upload_url', 'method', 'headers', 'storage_key', 'expires_at']);
    }

    public function test_ayni_id_icin_ayni_anahtar_uretilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $istek = [
            'id' => $id,
            'classroom_id' => $this->atanmis->id,
            'content_type' => 'image/jpeg',
            'byte_size' => 1000,
        ];

        // Adres yeniden istenebilmeli: imzali adresin suresi dolarsa istemci
        // bastan aliyor, anahtar degismemeli.
        $ilk = $this->postJson('/api/v1/photos/upload-url', $istek)->json('storage_key');
        $ikinci = $this->postJson('/api/v1/photos/upload-url', $istek)->json('storage_key');

        $this->assertSame($ilk, $ikinci);
    }

    public function test_atanmamis_sinif_icin_adres_verilmez(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/photos/upload-url', [
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->atanmamis->id,
            'content_type' => 'image/jpeg',
            'byte_size' => 1000,
        ])->assertStatus(403);
    }

    public function test_jpeg_disi_tur_reddedilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/photos/upload-url', [
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->atanmis->id,
            'content_type' => 'image/png',
            'byte_size' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('content_type');
    }

    public function test_cok_buyuk_dosya_adres_asamasinda_reddedilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/photos/upload-url', [
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->atanmis->id,
            'content_type' => 'image/jpeg',
            'byte_size' => Photo::MAX_BYTES + 1,
        ])->assertStatus(422)->assertJsonValidationErrors('byte_size');
    }

    // --- C: kesinlestirme ---

    public function test_fotograf_kesinlestirilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $response = $this->postJson('/api/v1/photos', $this->payload($id));

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.taken_at', '2026-08-23T10:30:00+00:00')
            ->assertJsonPath('data.child_ids', [$this->izinli->id]);

        $this->assertDatabaseCount('photos', 1);
        $this->assertDatabaseCount('child_photo', 1);
        $this->assertSame($this->papatya->id, Photo::withoutGlobalScopes()->sole()->institution_id);
    }

    public function test_etiketsiz_fotograf_kabul_edilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $this->postJson('/api/v1/photos', $this->payload($id, ['child_ids' => []]))
            ->assertStatus(201)
            ->assertJsonPath('data.child_ids', []);
    }

    public function test_ayni_fotograf_ikinci_kez_kesinlestirilemez(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $this->postJson('/api/v1/photos', $this->payload($id))->assertStatus(201);
        $this->postJson('/api/v1/photos', $this->payload($id))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->assertDatabaseCount('photos', 1);
    }

    public function test_izinsiz_cocuk_etiketlenemez(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $response = $this->postJson('/api/v1/photos', $this->payload($id, [
            'child_ids' => [$this->izinli->id, $this->izinsiz->id, $this->bekleyen->id],
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('child_ids')
            // Kalici: veli izni degismeden duzelmez.
            ->assertJsonPath('reason', 'consent_blocked');

        // Hangi cocuklarin engellendigi istemciye acikca bildirilir.
        $engellenen = $response->json('blocked_child_ids');
        sort($engellenen);
        $beklenen = [$this->izinsiz->id, $this->bekleyen->id];
        sort($beklenen);

        $this->assertSame($beklenen, $engellenen);
        $this->assertDatabaseCount('photos', 0);
    }

    public function test_baska_sinifin_cocugu_etiketlenemez(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $this->postJson('/api/v1/photos', $this->payload($id, [
            'child_ids' => [$this->digerSinifCocugu->id],
        ]))
            ->assertStatus(422)
            ->assertJsonPath('blocked_child_ids', [$this->digerSinifCocugu->id])
            ->assertJsonPath('reason', 'not_in_classroom');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_yuklenmemis_dosya_yeniden_yukleme_ister(): void
    {
        Sanctum::actingAs($this->ayse);

        // Dosya PUT edilmedi (ya da photos:prune sildi). Kalici degil:
        // istemci 1. adimdan tekrar denemeli.
        $this->postJson('/api/v1/photos', $this->payload((string) Str::uuid7()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('storage_key')
            ->assertJsonPath('reason', 'upload_incomplete');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_bos_dosya_yeniden_yukleme_ister(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        // PUT ortada kesildi: anahtar var ama icerik yok.
        $this->upload($id, '');

        $this->postJson('/api/v1/photos', $this->payload($id))
            ->assertStatus(422)
            ->assertJsonPath('reason', 'upload_incomplete');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_jpeg_olarak_baslamayan_dosya_yeniden_yukleme_ister(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        // Imzali yukleme rotasi icerige bakmadigi icin JPEG olmayan icerik
        // diske yazilabilir; tek denetim burasidir. Yarim inen govde de ayni
        // sekilde gorunur, o yuzden yeniden yukleme isteniyor.
        $this->upload($id, '%PDF-1.7 sahte dosya');

        $this->postJson('/api/v1/photos', $this->payload($id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('storage_key')
            ->assertJsonPath('reason', 'upload_incomplete');

        $this->assertDatabaseCount('photos', 0);
    }

    public function test_boyut_siniri_asan_dosya_kalici_reddedilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id, $this->jpeg(Photo::MAX_BYTES + 10));

        // Kalici: yeniden yuklemek duzeltmez, dosyanin kucultulmesi gerekir.
        $this->postJson('/api/v1/photos', $this->payload($id))
            ->assertStatus(422)
            ->assertJsonValidationErrors('storage_key')
            ->assertJsonPath('reason', 'too_large');
    }

    public function test_baskasinin_anahtari_sahiplenilemez(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $this->postJson('/api/v1/photos', $this->payload($id, [
            'storage_key' => 'photos/baska-kurum/baska-dosya.jpg',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('storage_key')
            ->assertJsonPath('reason', 'key_mismatch');
    }

    public function test_atanmamis_sinifa_fotograf_kesinlestirilemez(): void
    {
        Sanctum::actingAs($this->ayse);

        $id = (string) Str::uuid7();
        $this->upload($id);

        $this->postJson('/api/v1/photos', $this->payload($id, [
            'classroom_id' => $this->atanmamis->id,
            'child_ids' => [],
        ]))->assertStatus(403);
    }

    public function test_token_yoksa_401_doner(): void
    {
        $this->postJson('/api/v1/photos/upload-url', [
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->atanmis->id,
            'content_type' => 'image/jpeg',
            'byte_size' => 1000,
        ])->assertStatus(401);

        $this->postJson('/api/v1/photos', $this->payload((string) Str::uuid7()))
            ->assertStatus(401);
    }

    // --- temizlik komutu ---

    public function test_prune_yalnizca_sahipsiz_ve_eski_dosyayi_siler(): void
    {
        Sanctum::actingAs($this->ayse);

        // 1) Kesinlestirilmis: her zaman korunur.
        $kesinlesmis = (string) Str::uuid7();
        $this->upload($kesinlesmis);
        $this->postJson('/api/v1/photos', $this->payload($kesinlesmis))->assertStatus(201);

        // 2) Sahipsiz ama yeni: kesinlestirme yolda olabilir, korunur.
        $yeni = (string) Str::uuid7();
        $yeniKey = $this->upload($yeni);

        // 3) Sahipsiz ve eski: silinir.
        $eski = (string) Str::uuid7();
        $eskiKey = $this->upload($eski);
        touch(Storage::disk('local')->path($eskiKey), now()->subHours(72)->getTimestamp());

        $this->artisan('photos:prune', ['--hours' => 48])->assertExitCode(0);

        Storage::disk('local')->assertExists(Photo::storageKeyFor($this->papatya->id, $kesinlesmis));
        Storage::disk('local')->assertExists($yeniKey);
        Storage::disk('local')->assertMissing($eskiKey);
    }

    public function test_prune_dry_run_hicbir_seyi_silmez(): void
    {
        $eski = (string) Str::uuid7();
        $eskiKey = $this->upload($eski);
        touch(Storage::disk('local')->path($eskiKey), now()->subHours(72)->getTimestamp());

        $this->artisan('photos:prune', ['--hours' => 48, '--dry-run' => true])->assertExitCode(0);

        Storage::disk('local')->assertExists($eskiKey);
    }
}
