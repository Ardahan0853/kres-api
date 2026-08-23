<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecordTest extends TestCase
{
    use RefreshDatabase;

    private Institution $papatya;

    private Classroom $atanmis;

    private Classroom $atanmamis;

    private Child $atanmisCocuk;

    private Child $atanmamisCocuk;

    private User $ayse;

    private User $admin;

    private Institution $digerKurum;

    private Classroom $digerSinif;

    private Child $digerCocuk;

    private User $digerOgretmen;

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

        $this->atanmisCocuk = $this->child($this->atanmis, 'Deniz');
        $this->atanmamisCocuk = $this->child($this->atanmamis, 'Ada');

        $this->ayse = User::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->ayse->classrooms()->attach($this->atanmis->id);

        $this->admin = User::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Papatya Yönetici',
            'email' => 'admin@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->digerKurum = Institution::create(['name' => 'Test Kurum 2']);
        $this->digerSinif = Classroom::create([
            'institution_id' => $this->digerKurum->id,
            'name' => 'Kelebekler',
        ]);
        $this->digerCocuk = $this->child($this->digerSinif, 'Ege');
        $this->digerOgretmen = User::create([
            'institution_id' => $this->digerKurum->id,
            'name' => 'Diğer Öğretmen',
            'email' => 'diger@kurum2.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->digerOgretmen->classrooms()->attach($this->digerSinif->id);
    }

    private function child(Classroom $classroom, string $firstName): Child
    {
        return Child::create([
            'institution_id' => $classroom->institution_id,
            'classroom_id' => $classroom->id,
            'first_name' => $firstName,
            'last_name' => 'Yılmaz',
            'birth_date' => '2022-04-01',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->atanmis->id,
            'child_id' => $this->atanmisCocuk->id,
            'type' => 'attendance',
            'value' => ['status' => 'geldi'],
            'recorded_at' => '2026-08-23T09:12:31.000Z',
        ], $overrides);
    }

    public function test_ogretmen_atandigi_sinifa_kayit_yazabilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();

        $response = $this->postJson('/api/v1/records', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $payload['id'])
            ->assertJsonPath('data.type', 'attendance')
            ->assertJsonPath('data.value.status', 'geldi');

        $this->assertDatabaseCount('records', 1);
        $this->assertSame($this->papatya->id, Record::withoutGlobalScopes()->sole()->institution_id);
        $this->assertSame($this->ayse->id, Record::withoutGlobalScopes()->sole()->user_id);
    }

    public function test_recorded_at_sunucu_saatiyle_ezilmez(): void
    {
        Sanctum::actingAs($this->ayse);

        // Ogretmen sabah 09:12'de yoklama alir, aga cok sonra kavusur.
        $this->postJson('/api/v1/records', $this->payload([
            'recorded_at' => '2026-08-23T09:12:31.000Z',
        ]))->assertStatus(201);

        $record = Record::withoutGlobalScopes()->sole();

        $this->assertSame('2026-08-23 09:12:31', $record->recorded_at->utc()->format('Y-m-d H:i:s'));
        // Sunucunun teslim aldigi an ayri tutulur.
        $this->assertNotSame(
            $record->recorded_at->utc()->format('Y-m-d H:i:s'),
            $record->created_at->utc()->format('Y-m-d H:i:s')
        );
    }

    public function test_ayni_id_ikinci_kez_geldiginde_yeni_kayit_acilmaz(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();

        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        // Kuyruk zaman asimindan sonra ayni kaydi tekrar gonderir.
        $this->postJson('/api/v1/records', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $payload['id']);

        $this->assertDatabaseCount('records', 1);
    }

    public function test_tekrar_gonderimde_deger_guncellenir(): void
    {
        Sanctum::actingAs($this->ayse);

        // Ogretmen 12:05'te "Yedi" isaretler.
        $payload = $this->payload([
            'type' => 'meal',
            'value' => ['meal' => 'lunch', 'amount' => 'all'],
            'recorded_at' => '2026-08-23T12:05:00.000Z',
        ]);
        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        // 12:15'te "Az yedi"ye duzeltir: ayni id, yeni govde.
        $this->postJson('/api/v1/records', array_merge($payload, [
            'value' => ['meal' => 'lunch', 'amount' => 'some'],
            'recorded_at' => '2026-08-23T12:15:00.000Z',
        ]))
            ->assertStatus(200)
            ->assertJsonPath('data.value.amount', 'some')
            ->assertJsonPath('data.recorded_at', '2026-08-23T12:15:00+00:00');

        $this->assertDatabaseCount('records', 1);
        $this->assertSame('some', Record::withoutGlobalScopes()->sole()->value['amount']);
    }

    public function test_gecikmis_eski_istek_yeni_degeri_ezmez(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload([
            'type' => 'meal',
            'value' => ['meal' => 'lunch', 'amount' => 'all'],
            'recorded_at' => '2026-08-23T12:05:00.000Z',
        ]);

        // Duzeltme once varir.
        $this->postJson('/api/v1/records', array_merge($payload, [
            'value' => ['meal' => 'lunch', 'amount' => 'some'],
            'recorded_at' => '2026-08-23T12:15:00.000Z',
        ]))->assertStatus(201);

        // Agda gecikmis ilk istek sonra varir: 200 alir ama satiri ezmez.
        $this->postJson('/api/v1/records', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.value.amount', 'some')
            ->assertJsonPath('data.recorded_at', '2026-08-23T12:15:00+00:00');

        $this->assertSame('some', Record::withoutGlobalScopes()->sole()->value['amount']);
    }

    public function test_geri_al_ayni_damgayla_uygulanir(): void
    {
        Sanctum::actingAs($this->ayse);

        // "Geri al" onceki degeri KENDI eski damgasiyla geri yazar; damga
        // saklananla esit oldugu icin guard bunu yutmamali.
        $payload = $this->payload([
            'type' => 'toilet',
            'value' => ['kind' => 'diaper'],
            'recorded_at' => '2026-08-23T11:00:00.000Z',
        ]);
        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        $this->postJson('/api/v1/records', array_merge($payload, [
            'value' => ['kind' => 'toilet'],
        ]))
            ->assertStatus(200)
            ->assertJsonPath('data.value.kind', 'toilet');

        $this->assertSame('toilet', Record::withoutGlobalScopes()->sole()->value['kind']);
    }

    public function test_guncellemede_created_at_korunur(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();
        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        $ilkCreatedAt = Record::withoutGlobalScopes()->sole()->created_at;

        $this->travel(5)->minutes();

        $this->postJson('/api/v1/records', array_merge($payload, [
            'value' => ['present' => false],
        ]))->assertStatus(200);

        $record = Record::withoutGlobalScopes()->sole();

        $this->assertTrue($ilkCreatedAt->equalTo($record->created_at));
        $this->assertTrue($record->updated_at->greaterThan($record->created_at));
    }

    public function test_uyku_kaydi_ayni_id_ile_kapanir(): void
    {
        Sanctum::actingAs($this->ayse);

        // "Uyudu" dokunusu: kayit acilir, ended_at bos.
        $payload = $this->payload([
            'type' => 'nap',
            'value' => ['started_at' => '2026-08-23T13:00:00.000Z', 'ended_at' => null],
            'recorded_at' => '2026-08-23T13:00:00.000Z',
        ]);
        $this->postJson('/api/v1/records', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.value.ended_at', null);

        // "Uyandi" dokunusu: AYNI id, ended_at dolu.
        $this->postJson('/api/v1/records', array_merge($payload, [
            'value' => [
                'started_at' => '2026-08-23T13:00:00.000Z',
                'ended_at' => '2026-08-23T14:20:00.000Z',
            ],
            'recorded_at' => '2026-08-23T14:20:00.000Z',
        ]))
            ->assertStatus(200)
            ->assertJsonPath('data.value.started_at', '2026-08-23T13:00:00.000Z')
            ->assertJsonPath('data.value.ended_at', '2026-08-23T14:20:00.000Z');

        $this->assertDatabaseCount('records', 1);
    }

    public function test_kayit_baska_cocuga_veya_sinifa_tasinamaz(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = $this->payload();
        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        // Ayni id, farkli cocuk: sessizce tasimak yerine reddedilir.
        $this->postJson('/api/v1/records', array_merge($payload, [
            'classroom_id' => $this->atanmamis->id,
            'child_id' => $this->atanmamisCocuk->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('classroom_id');

        // Ayni id, ayni sinif, farkli tur.
        $this->postJson('/api/v1/records', array_merge($payload, [
            'type' => 'nap',
        ]))->assertStatus(422)->assertJsonValidationErrors('type');

        $record = Record::withoutGlobalScopes()->sole();
        $this->assertSame($this->atanmisCocuk->id, $record->child_id);
        $this->assertSame('attendance', $record->type);
    }

    public function test_atanmamis_sinifa_yazmaya_calisan_ogretmen_403_alir(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/records', $this->payload([
            'classroom_id' => $this->atanmamis->id,
            'child_id' => $this->atanmamisCocuk->id,
        ]))->assertStatus(403);

        $this->assertDatabaseCount('records', 0);
    }

    public function test_admin_kurumun_her_sinifina_yazabilir(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/records', $this->payload([
            'classroom_id' => $this->atanmamis->id,
            'child_id' => $this->atanmamisCocuk->id,
        ]))->assertStatus(201);
    }

    public function test_cocuk_baska_sinifa_aitse_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/records', $this->payload([
            'child_id' => $this->atanmamisCocuk->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('child_id');

        $this->assertDatabaseCount('records', 0);
    }

    public function test_baska_kurumun_sinifi_404_degil_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        // 404 istemcide "uc henuz yok" demek; buraya asla dusmemeli.
        $this->postJson('/api/v1/records', $this->payload([
            'classroom_id' => $this->digerSinif->id,
            'child_id' => $this->digerCocuk->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('classroom_id');

        $this->assertDatabaseCount('records', 0);
    }

    public function test_baska_kuruma_ait_id_409_doner_ve_kayit_sizmaz(): void
    {
        Sanctum::actingAs($this->digerOgretmen);

        $payload = [
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->digerSinif->id,
            'child_id' => $this->digerCocuk->id,
            'type' => 'note',
            'value' => ['text' => 'gizli'],
            'recorded_at' => '2026-08-23T09:12:31.000Z',
        ];
        $this->postJson('/api/v1/records', $payload)->assertStatus(201);

        Sanctum::actingAs($this->ayse);

        $response = $this->postJson('/api/v1/records', $this->payload([
            'id' => $payload['id'],
        ]));

        $response->assertStatus(409);
        $this->assertStringNotContainsString('gizli', $response->getContent());
        $this->assertSame(1, Record::withoutGlobalScopes()->count());
    }

    public function test_gecersiz_tur_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/records', $this->payload(['type' => 'mood']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_dogrulama_mesajlari_turkce_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        // Istemci 422 gövdesindeki mesaji ogretmene aynen gosteriyor, bu yuzden
        // hicbir dogrulama yolundan Ingilizce varsayilan mesaj cikmamali.
        $bozukTurler = [
            ['type' => 123],
            ['type' => ['attendance']],
            ['type' => 'mood'],
        ];

        foreach ($bozukTurler as $override) {
            $response = $this->postJson('/api/v1/records', $this->payload($override));

            $response->assertStatus(422);

            foreach ($response->json('errors.type') as $mesaj) {
                $this->assertStringNotContainsString('The type field', $mesaj);
                $this->assertSame('Geçersiz kayıt türü.', $mesaj);
            }
        }
    }

    public function test_value_null_olabilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/records', $this->payload(['value' => null]))
            ->assertStatus(201)
            ->assertJsonPath('data.value', null);
    }

    public function test_token_yoksa_401_doner(): void
    {
        $this->postJson('/api/v1/records', $this->payload())->assertStatus(401);
    }

    public function test_toplu_gonderimde_her_kayit_kendi_sonucunu_alir(): void
    {
        Sanctum::actingAs($this->ayse);

        $saglam = $this->payload();
        $tekrar = $this->payload();
        $this->postJson('/api/v1/records', $tekrar)->assertStatus(201);

        $response = $this->postJson('/api/v1/records/batch', [
            'records' => [
                $saglam,
                $tekrar,
                // Bozuk kayit digerlerini dusurmemeli.
                $this->payload(['type' => 'mood']),
                $this->payload([
                    'classroom_id' => $this->atanmamis->id,
                    'child_id' => $this->atanmamisCocuk->id,
                ]),
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('results.0.status', 201)
            ->assertJsonPath('results.0.result', 'created')
            ->assertJsonPath('results.1.status', 200)
            ->assertJsonPath('results.1.result', 'updated')
            ->assertJsonPath('results.2.status', 422)
            ->assertJsonPath('results.2.result', 'invalid')
            ->assertJsonPath('results.3.status', 403)
            ->assertJsonPath('results.3.result', 'forbidden');

        // Yalnizca saglam olan ikisi yazildi.
        $this->assertDatabaseCount('records', 2);
    }

    public function test_toplu_gonderimde_id_ve_sira_geri_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $bir = $this->payload();
        $iki = $this->payload();

        $this->postJson('/api/v1/records/batch', ['records' => [$bir, $iki]])
            ->assertStatus(200)
            ->assertJsonPath('results.0.id', $bir['id'])
            ->assertJsonPath('results.0.index', 0)
            ->assertJsonPath('results.1.id', $iki['id'])
            ->assertJsonPath('results.1.index', 1);
    }

    public function test_bos_toplu_gonderim_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/records/batch', ['records' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('records');
    }

    public function test_toplu_gonderim_token_yoksa_401_doner(): void
    {
        $this->postJson('/api/v1/records/batch', ['records' => [$this->payload()]])
            ->assertStatus(401);
    }
}
