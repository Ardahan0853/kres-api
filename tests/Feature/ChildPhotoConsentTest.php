<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChildPhotoConsentTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $sinif;

    private User $ayse;

    protected function setUp(): void
    {
        parent::setUp();

        $kurum = Institution::create(['name' => 'Papatya Anaokulu']);

        $this->sinif = Classroom::create([
            'institution_id' => $kurum->id,
            'name' => 'Papatyalar',
        ]);

        $this->ayse = User::create([
            'institution_id' => $kurum->id,
            'name' => 'Ayşe Öğretmen',
            'email' => 'ayse@papatya.test',
            'password' => 'password',
            'role' => User::ROLE_TEACHER,
        ]);
        $this->ayse->classrooms()->attach($this->sinif->id);
    }

    private function child(string $firstName, string $consent): Child
    {
        return Child::create([
            'institution_id' => $this->sinif->institution_id,
            'classroom_id' => $this->sinif->id,
            'first_name' => $firstName,
            'last_name' => 'Yılmaz',
            'birth_date' => '2022-04-01',
            'photo_consent' => $consent,
        ]);
    }

    public function test_cocuk_listesi_photo_consent_doner(): void
    {
        $this->child('Deniz', Child::CONSENT_GRANTED);
        $this->child('Ada', Child::CONSENT_DENIED);
        $this->child('Ege', Child::CONSENT_PENDING);

        Sanctum::actingAs($this->ayse);

        $response = $this->getJson("/api/v1/classrooms/{$this->sinif->id}/children");

        $response->assertStatus(200)
            // first_name'e gore siralaniyor: Ada, Deniz, Ege
            ->assertJsonPath('data.0.photo_consent', Child::CONSENT_DENIED)
            ->assertJsonPath('data.1.photo_consent', Child::CONSENT_GRANTED)
            ->assertJsonPath('data.2.photo_consent', Child::CONSENT_PENDING);
    }

    public function test_photo_consent_varsayilani_pending(): void
    {
        // Izin alinmamis kabul etmek guvenli varsayilan.
        $child = Child::create([
            'institution_id' => $this->sinif->institution_id,
            'classroom_id' => $this->sinif->id,
            'first_name' => 'Nehir',
            'last_name' => 'Kaya',
            'birth_date' => '2022-04-01',
        ]);

        $this->assertSame(Child::CONSENT_PENDING, $child->fresh()->photo_consent);
        $this->assertFalse($child->fresh()->hasPhotoConsent());
    }

    public function test_dagitim_her_sinifta_denied_ve_pending_garanti_eder(): void
    {
        // En kucuk sinif 8 cocuk; ucu de bulunmali ki izin akisi denenebilsin.
        foreach ([8, 9, 10, 11, 12] as $adet) {
            $dagitim = [];

            for ($i = 0; $i < $adet; $i++) {
                $dagitim[] = Child::consentForIndex($i);
            }

            $this->assertContains(Child::CONSENT_DENIED, $dagitim, "{$adet} kisilik sinifta denied yok");
            $this->assertContains(Child::CONSENT_PENDING, $dagitim, "{$adet} kisilik sinifta pending yok");
            $this->assertContains(Child::CONSENT_GRANTED, $dagitim, "{$adet} kisilik sinifta granted yok");
        }
    }
}
