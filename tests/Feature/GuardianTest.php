<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    private Institution $papatya;

    private Classroom $sinif;

    private Child $deniz;

    private Child $ada;

    private User $ayse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->papatya = Institution::create(['name' => 'Papatya Anaokulu']);

        $this->sinif = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Papatyalar',
        ]);

        $this->deniz = $this->child('Deniz');
        $this->ada = $this->child('Ada');

        $this->ayse = User::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->ayse->classrooms()->attach($this->sinif->id);
    }

    private function child(string $ad): Child
    {
        return Child::create([
            'institution_id' => $this->papatya->id,
            'classroom_id' => $this->sinif->id,
            'first_name' => $ad,
            'last_name' => 'Yılmaz',
            'birth_date' => '2022-04-01',
            'photo_consent' => Child::CONSENT_GRANTED,
        ]);
    }

    private function guardian(string $ad, string $phone): Guardian
    {
        $g = new Guardian(['name' => $ad, 'phone' => Guardian::normalizePhone($phone), 'phone_raw' => $phone]);
        $g->institution_id = $this->papatya->id;
        $g->save();

        return $g;
    }

    public function test_telefon_e164_e_normalize_edilir(): void
    {
        $this->assertSame('+905525700853', Guardian::normalizePhone('05525700853'));
        $this->assertSame('+905525700853', Guardian::normalizePhone('5525700853'));
        $this->assertSame('+905525700853', Guardian::normalizePhone('905525700853'));
        $this->assertSame('+905525700853', Guardian::normalizePhone('+90 552 570 08 53'));
        $this->assertSame('+905525700853', Guardian::normalizePhone('0552 570 08 53'));

        // Cozulemeyen girdi uydurulmaz.
        $this->assertNull(Guardian::normalizePhone('123'));
        $this->assertNull(Guardian::normalizePhone('merhaba'));
    }

    public function test_telefon_maskelenir(): void
    {
        $g = $this->guardian('Test Veli', '05525700853');

        $this->assertSame('+90552*****53', $g->maskedPhone());
    }

    public function test_bir_cocugun_birden_fazla_velisi_olabilir(): void
    {
        $anne = $this->guardian('Anne', '05525700853');
        $baba = $this->guardian('Baba', '05325700854');

        $this->deniz->parents()->attach([
            $anne->id => ['relation' => 'anne'],
            $baba->id => ['relation' => 'baba'],
        ]);

        $this->assertCount(2, $this->deniz->parents()->get());
        $this->assertSame('anne', $this->deniz->parents()->where('parents.id', $anne->id)->first()->pivot->relation);
    }

    public function test_kardeslerde_veli_tek_sayilir(): void
    {
        // Ayni veli iki cocuga bagli: parent_count 2 degil 1 olmali.
        $veli = $this->guardian('Ortak Veli', '05525700853');
        $this->deniz->parents()->attach($veli->id);
        $this->ada->parents()->attach($veli->id);

        Sanctum::actingAs($this->ayse);

        $this->getJson('/api/v1/classrooms')
            ->assertStatus(200)
            ->assertJsonPath('data.0.children_count', 2)
            ->assertJsonPath('data.0.parent_count', 1);
    }

    public function test_veli_yoksa_parent_count_sifir(): void
    {
        Sanctum::actingAs($this->ayse);

        $this->getJson('/api/v1/classrooms')
            ->assertStatus(200)
            ->assertJsonPath('data.0.parent_count', 0);
    }

    public function test_day_send_gercek_veli_sayisi_doner(): void
    {
        $anne = $this->guardian('Anne', '05525700853');
        $baba = $this->guardian('Baba', '05325700854');
        $this->deniz->parents()->attach([$anne->id, $baba->id]);
        $this->ada->parents()->attach($anne->id);

        Sanctum::actingAs($this->ayse);

        // 2 cocuk, 3 baglanti ama 2 DISTINCT veli.
        $this->postJson("/api/v1/classrooms/{$this->sinif->id}/day-send", [
            'id' => (string) Str::uuid7(),
            'day' => '2026-08-23',
            'requested_at' => '2026-08-23T17:12:00.000Z',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.child_count', 2)
            ->assertJsonPath('data.parent_count', 2);
    }

    public function test_veli_telefonu_baska_kuruma_sizmaz(): void
    {
        $this->guardian('Papatya Velisi', '05525700853');

        $digerKurum = Institution::create(['name' => 'Test Kurum 2']);
        $digerOgretmen = User::create([
            'institution_id' => $digerKurum->id,
            'name' => 'Diğer Öğretmen',
            'email' => 'diger@kurum2.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);

        Sanctum::actingAs($digerOgretmen);

        // Global scope veli sorgusunu da kuruma baglar.
        $this->assertSame(0, Guardian::query()->count());
    }
}
