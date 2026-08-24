<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionLogTest extends TestCase
{
    use RefreshDatabase;

    private User $ayse;

    private User $mehmet;

    protected function setUp(): void
    {
        parent::setUp();

        $papatya = Institution::create(['name' => 'Papatya Anaokulu']);

        $this->ayse = $this->user($papatya, 'ayse@papatya.test', 'Ayşe Öğretmen');
        $this->mehmet = $this->user($papatya, 'mehmet@papatya.test', 'Mehmet Öğretmen');
    }

    private function user(Institution $kurum, string $email, string $ad): User
    {
        return User::create([
            'institution_id' => $kurum->id,
            'name' => $ad,
            'email' => $email,
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'started_at' => '2026-08-24T09:00:00.000Z',
            'offline' => true,
        ], $overrides);
    }

    public function test_giris_kaydi_yazilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();

        $this->postJson('/api/v1/auth/session-log', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.id', $payload['id'])
            ->assertJsonPath('data.started_at', '2026-08-24T09:00:00+00:00')
            ->assertJsonPath('data.offline', true);

        $kayit = SessionLog::withoutGlobalScopes()->sole();
        $this->assertSame($this->ayse->id, $kayit->user_id);
        $this->assertSame($this->ayse->institution_id, $kayit->institution_id);
    }

    public function test_started_at_sunucu_saatiyle_ezilmez(): void
    {
        Sanctum::actingAs($this->ayse);

        // 09:00'da cevrimdisi giren ogretmen 11:00'da baglanir.
        $this->postJson('/api/v1/auth/session-log', $this->payload([
            'started_at' => '2026-08-24T09:00:00.000Z',
        ]))->assertStatus(201);

        $kayit = SessionLog::withoutGlobalScopes()->sole();

        $this->assertSame('2026-08-24 09:00:00', $kayit->started_at->utc()->format('Y-m-d H:i:s'));
        // Sunucunun teslim aldigi an ayri tutulur.
        $this->assertTrue($kayit->created_at->greaterThan($kayit->started_at));
    }

    public function test_ayni_id_ikinci_kez_yeni_satir_acmaz(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();

        $this->postJson('/api/v1/auth/session-log', $payload)->assertStatus(201);
        $this->postJson('/api/v1/auth/session-log', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $payload['id']);

        $this->assertDatabaseCount('session_logs', 1);
    }

    public function test_baska_ogretmenin_kaydi_sizmaz(): void
    {
        Sanctum::actingAs($this->ayse);
        $payload = $this->payload(['started_at' => '2026-08-24T07:30:00.000Z']);
        $this->postJson('/api/v1/auth/session-log', $payload)->assertStatus(201);

        // Mehmet ayni id ile gonderirse Ayse'nin giris saatini gormemeli.
        Sanctum::actingAs($this->mehmet);

        $response = $this->postJson('/api/v1/auth/session-log', $payload);

        $response->assertStatus(409);
        $this->assertStringNotContainsString('07:30', $response->getContent());
        $this->assertDatabaseCount('session_logs', 1);
    }

    public function test_offline_alani_gelmezse_false_sayilir(): void
    {
        Sanctum::actingAs($this->ayse);

        $payload = $this->payload();
        unset($payload['offline']);

        // Gevsek: eksik alan yuzunden 422 donseydik kayit kuyrukta
        // sonsuza kadar tekrar denenirdi.
        $this->postJson('/api/v1/auth/session-log', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.offline', false);
    }

    public function test_gecersiz_govde_422_doner(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->postJson('/api/v1/auth/session-log', $this->payload(['id' => 'uuid-degil']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->postJson('/api/v1/auth/session-log', $this->payload(['started_at' => 'dun']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('started_at');
    }

    public function test_token_yoksa_401_doner(): void
    {
        $this->postJson('/api/v1/auth/session-log', $this->payload())->assertStatus(401);
    }
}
