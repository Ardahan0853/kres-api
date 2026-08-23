<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\DaySend;
use App\Models\Institution;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DaySendTest extends TestCase
{
    use RefreshDatabase;

    private Institution $papatya;

    private Classroom $atanmis;

    private Classroom $atanmamis;

    private User $ayse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->papatya = Institution::create(['name' => 'Papatya Anaokulu']);

        $this->atanmis = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Papatyalar',
        ]);

        $this->atanmamis = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Menekşeler',
        ]);

        foreach (['Deniz', 'Ada', 'Ege'] as $ad) {
            Child::create([
                'institution_id' => $this->papatya->id,
                'classroom_id' => $this->atanmis->id,
                'first_name' => $ad,
                'last_name' => 'Yılmaz',
                'birth_date' => '2022-04-01',
                'photo_consent' => Child::CONSENT_GRANTED,
            ]);
        }

        $this->ayse = User::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->ayse->classrooms()->attach($this->atanmis->id);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'day' => '2026-08-23',
            'requested_at' => '2026-08-23T17:12:00.000Z',
        ], $overrides);
    }

    private function url(?Classroom $classroom = null): string
    {
        $classroom ??= $this->atanmis;

        return "/api/v1/classrooms/{$classroom->id}/day-send";
    }

    private function photo(string $takenAt): Photo
    {
        $id = (string) Str::uuid7();

        return Photo::create([
            'id' => $id,
            'classroom_id' => $this->atanmis->id,
            'user_id' => $this->ayse->id,
            'storage_key' => Photo::storageKeyFor($this->papatya->id, $id),
            'byte_size' => 1000,
            'taken_at' => $takenAt,
        ]);
    }

    public function test_gun_gonderilir_ve_ozet_doner(): void
    {
        // institution_id BelongsToInstitution trait'i uzerinden giris yapmis
        // kullanicidan yazildigi icin fotograflar actingAs'ten SONRA olusur.
        Sanctum::actingAs($this->ayse);

        $this->photo('2026-08-23T10:00:00.000Z');
        $this->photo('2026-08-23T14:00:00.000Z');
        // Baska gunun fotografi sayilmamali.
        $this->photo('2026-08-22T10:00:00.000Z');

        $payload = $this->payload();

        $response = $this->postJson($this->url(), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $payload['id'])
            ->assertJsonPath('data.day', '2026-08-23')
            ->assertJsonPath('data.child_count', 3)
            ->assertJsonPath('data.photo_count', 2)
            // Veli verisi bu semada yok; uydurma sayi yerine null.
            ->assertJsonPath('data.parent_count', null);

        $this->assertDatabaseCount('day_sends', 1);
    }

    public function test_requested_at_sunucu_saatiyle_ezilmez(): void
    {
        Sanctum::actingAs($this->ayse);

        // Ogretmen 17:12'de bahcede basar, aga cok sonra kavusur.
        $this->postJson($this->url(), $this->payload([
            'requested_at' => '2026-08-23T17:12:00.000Z',
        ]))->assertStatus(201)
            ->assertJsonPath('data.requested_at', '2026-08-23T17:12:00+00:00');

        $kayit = DaySend::withoutGlobalScopes()->sole();

        $this->assertSame('2026-08-23 17:12:00', $kayit->requested_at->utc()->format('Y-m-d H:i:s'));
        // sent_at sunucunun damgasidir, requested_at ile karistirilmaz.
        $this->assertTrue($kayit->sent_at->greaterThan($kayit->requested_at));
    }

    public function test_ayni_id_ikinci_kez_gelirse_yeni_gonderim_olmaz(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();

        $this->postJson($this->url(), $payload)->assertStatus(201);
        $this->postJson($this->url(), $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $payload['id']);

        $this->assertDatabaseCount('day_sends', 1);
    }

    public function test_ayni_gun_farkli_id_ile_gelirse_409_degil_200_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $ilk = $this->payload();
        $this->postJson($this->url(), $ilk)->assertStatus(201);

        // Ogretmen ikinci kez bastu. 409 donseydik istemci "gonderilemedi"
        // derdi, halbuki gun gitmisti.
        $this->postJson($this->url(), $this->payload(['day' => '2026-08-23']))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $ilk['id']);

        $this->assertDatabaseCount('day_sends', 1);
    }

    public function test_farkli_gun_yeni_gonderim_acar(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson($this->url(), $this->payload(['day' => '2026-08-23']))->assertStatus(201);
        $this->postJson($this->url(), $this->payload([
            'day' => '2026-08-24',
            'requested_at' => '2026-08-24T17:12:00.000Z',
        ]))->assertStatus(201);

        $this->assertDatabaseCount('day_sends', 2);
    }

    public function test_atanmamis_sinif_icin_403_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson($this->url($this->atanmamis), $this->payload())
            ->assertStatus(403);

        $this->assertDatabaseCount('day_sends', 0);
    }

    public function test_bilinmeyen_sinif_404_degil_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        // 404 istemcide "uc henuz yok" demek; buraya asla dusmemeli.
        $this->postJson('/api/v1/classrooms/'.Str::uuid7().'/day-send', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('reason', 'classroom_not_found');
    }

    public function test_gecersiz_gun_bicimi_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson($this->url(), $this->payload(['day' => '23.08.2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('day');
    }

    public function test_token_yoksa_401_doner(): void
    {
        $this->postJson($this->url(), $this->payload())->assertStatus(401);
    }

    public function test_sinif_listesi_day_sent_at_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        // Once gonderim yokken null olmali.
        $this->getJson('/api/v1/classrooms')
            ->assertStatus(200)
            ->assertJsonPath('data.0.day_sent_at', null);

        $this->postJson($this->url(), $this->payload([
            'day' => now()->toDateString(),
        ]))->assertStatus(201);

        $response = $this->getJson('/api/v1/classrooms')->assertStatus(200);

        $this->assertNotNull($response->json('data.0.day_sent_at'));
        // Mevcut alanlar bozulmamali.
        $response->assertJsonPath('data.0.name', 'Papatyalar')
            ->assertJsonPath('data.0.children_count', 3);
    }

    public function test_dunun_gonderimi_bugunun_seridini_yakmaz(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson($this->url(), $this->payload([
            'day' => now()->subDay()->toDateString(),
        ]))->assertStatus(201);

        $this->getJson('/api/v1/classrooms')
            ->assertStatus(200)
            ->assertJsonPath('data.0.day_sent_at', null);
    }
}
