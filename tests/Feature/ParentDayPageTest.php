<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\MagicLink;
use App\Models\Photo;
use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentDayPageTest extends TestCase
{
    use RefreshDatabase;

    private Institution $papatya;

    private Classroom $sinif;

    private Child $defne;

    private Child $baskaCocuk;

    private Guardian $veli;

    private string $gun = '2026-08-23';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->papatya = Institution::create(['name' => 'Papatya Anaokulu']);
        $this->sinif = Classroom::create([
            'institution_id' => $this->papatya->id,
            'name' => 'Papatyalar',
        ]);

        $this->defne = $this->child('Defne', 'Özkan', Child::CONSENT_GRANTED);
        $this->baskaCocuk = $this->child('Gizli', 'Çocuk', Child::CONSENT_GRANTED);

        $this->veli = new Guardian([
            'name' => 'Test Veli',
            'phone' => '+905525700853',
            'phone_raw' => '05525700853',
        ]);
        $this->veli->institution_id = $this->papatya->id;
        $this->veli->save();
        $this->veli->children()->attach($this->defne->id, ['relation' => 'anne']);
    }

    private function child(string $ad, string $soyad, string $consent): Child
    {
        return Child::create([
            'institution_id' => $this->papatya->id,
            'classroom_id' => $this->sinif->id,
            'first_name' => $ad,
            'last_name' => $soyad,
            'birth_date' => '2022-04-01',
            'photo_consent' => $consent,
        ]);
    }

    private function record(Child $child, string $type, ?array $value, string $at): Record
    {
        $r = new Record([
            'id' => (string) Str::uuid7(),
            'classroom_id' => $this->sinif->id,
            'child_id' => $child->id,
            'type' => $type,
            'value' => $value,
            'recorded_at' => $at,
        ]);
        $r->institution_id = $this->papatya->id;
        $r->save();

        return $r;
    }

    private function photo(Child $child, string $takenAt): Photo
    {
        $id = (string) Str::uuid7();
        $key = Photo::storageKeyFor($this->papatya->id, $id);
        Storage::disk('local')->put($key, "\xFF\xD8\xFF".str_repeat('a', 50));

        $photo = new Photo([
            'id' => $id,
            'classroom_id' => $this->sinif->id,
            'storage_key' => $key,
            'byte_size' => 53,
            'taken_at' => $takenAt,
        ]);
        $photo->institution_id = $this->papatya->id;
        $photo->save();
        $photo->children()->attach($child->id);

        return $photo;
    }

    private function link(?Child $child = null, ?string $gun = null): string
    {
        [, $token] = MagicLink::issue($this->veli, $child ?? $this->defne, $gun ?? $this->gun);

        return $token;
    }

    public function test_gecerli_baglanti_gunun_ozetini_gosterir(): void
    {
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $this->record($this->defne, 'meal', ['meal' => 'lunch', 'amount' => 'all'], '2026-08-23T09:00:00Z');
        $this->record($this->defne, 'toilet', ['kind' => 'diaper'], '2026-08-23T10:00:00Z');

        $response = $this->get('/v/'.$this->link());

        $response->assertStatus(200)
            ->assertSee('Defne')
            ->assertSee('Papatyalar')
            ->assertSee('Geldi')
            ->assertSee('Yedi')
            ->assertSee('1 bez', false);
    }

    public function test_uc_ogun_de_gosterilir(): void
    {
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $this->record($this->defne, 'meal', ['meal' => 'breakfast', 'amount' => 'all'], '2026-08-23T06:30:00Z');
        $this->record($this->defne, 'meal', ['meal' => 'lunch', 'amount' => 'some'], '2026-08-23T09:00:00Z');
        // "snack" = ikindi.
        $this->record($this->defne, 'meal', ['meal' => 'snack', 'amount' => 'none'], '2026-08-23T12:30:00Z');

        $response = $this->get('/v/'.$this->link())->assertStatus(200);

        $response->assertSee('Kahvaltı')->assertSee('Öğle yemeği')->assertSee('İkindi');
        $response->assertSee('Yedi')->assertSee('Az yedi')->assertSee('Yemedi');
    }

    public function test_ikindi_kaydi_yoksa_isaretlenmemis_yazar(): void
    {
        $this->record($this->defne, 'meal', ['meal' => 'lunch', 'amount' => 'all'], '2026-08-23T09:00:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('İkindi')
            ->assertSee('İşaretlenmemiş');
    }

    public function test_saatler_yerel_dilimde_gosterilir(): void
    {
        // 06:10 UTC = 09:10 Europe/Istanbul
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('09:10')
            ->assertDontSee('06:10');
    }

    public function test_ayni_turde_son_kayit_gecerlidir(): void
    {
        // Ogretmen once "geldi" isaretleyip sonra duzeltmis.
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $this->record($this->defne, 'attendance', ['present' => false], '2026-08-23T06:20:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('Gelmedi');
    }

    public function test_baska_cocugun_verisi_gorunmez(): void
    {
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $this->record($this->baskaCocuk, 'attendance', ['present' => true], '2026-08-23T06:15:00Z');
        $this->record($this->baskaCocuk, 'toilet', ['kind' => 'toilet'], '2026-08-23T07:00:00Z');
        $this->photo($this->baskaCocuk, '2026-08-23T08:00:00Z');

        $response = $this->get('/v/'.$this->link());

        $response->assertStatus(200)
            ->assertSee('Defne')
            // Ne ismi, ne sayilari, ne fotografi.
            ->assertDontSee('Gizli')
            ->assertDontSee('1 tuvalet', false);

        $this->assertStringNotContainsString('Fotoğraflar', $response->getContent());
    }

    public function test_yalnizca_o_gunun_kayitlari_gorunur(): void
    {
        $this->record($this->defne, 'meal', ['meal' => 'lunch', 'amount' => 'all'], '2026-08-23T09:00:00Z');
        // Ertesi gunun kaydi bu sayfaya girmemeli.
        $this->record($this->defne, 'toilet', ['kind' => 'toilet'], '2026-08-24T09:00:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('Yedi')
            ->assertSee('Kayıt yok');
    }

    public function test_kayit_yoksa_uydurmaz(): void
    {
        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('henüz kayıt girilmemiş', false);
    }

    public function test_gecersiz_token_sakin_sayfa_gosterir(): void
    {
        $this->get('/v/uydurma-token')
            ->assertStatus(404)
            ->assertSee('süresi dolmuş', false)
            ->assertDontSee('Defne');
    }

    public function test_suresi_dolmus_baglanti_calismaz(): void
    {
        [$link, $token] = MagicLink::issue($this->veli, $this->defne, $this->gun);
        $link->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get('/v/'.$token)
            ->assertStatus(404)
            ->assertDontSee('Defne');
    }

    public function test_ham_token_veritabaninda_saklanmaz(): void
    {
        [$link, $token] = MagicLink::issue($this->veli, $this->defne, $this->gun);

        $this->assertDatabaseMissing('magic_links', ['token_hash' => $token]);
        $this->assertSame(hash('sha256', $token), DB::table('magic_links')->where('id', $link->id)->value('token_hash'));
        $this->assertSame(43, strlen($token));
    }

    public function test_izinli_cocugun_fotografi_gorunur_izinsizin_gorunmez(): void
    {
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $this->photo($this->defne, '2026-08-23T08:00:00Z');

        $response = $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('Fotoğraflar', false);

        // Adres imzali ve sureli olmali; ciplak dosya yolu tek basina ise yaramaz.
        $this->assertMatchesRegularExpression(
            '/<img src="[^"]*(signature|expiration)=/',
            $response->getContent()
        );

        // Izin geri alinirsa eski fotograf da gorunmez.
        $this->defne->update(['photo_consent' => Child::CONSENT_DENIED]);

        $response = $this->get('/v/'.$this->link());
        $this->assertStringNotContainsString('Fotoğraflar', $response->getContent());
    }

    public function test_silinen_kayit_veli_sayfasinda_gorunmez(): void
    {
        $this->record($this->defne, 'attendance', ['present' => true], '2026-08-23T06:10:00Z');
        $yanlis = $this->record($this->defne, 'toilet', ['kind' => 'diaper'], '2026-08-23T07:00:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('1 bez', false);

        // Ogretmen yanlislikla girdigi kaydi siler.
        $yanlis->delete();

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertDontSee('1 bez', false)
            ->assertSee('Kayıt yok');
    }

    public function test_gelmeyen_cocukta_bos_satirlar_gizlenir(): void
    {
        $this->record($this->defne, 'attendance', ['present' => false], '2026-08-23T06:10:00Z');

        $response = $this->get('/v/'.$this->link())->assertStatus(200);

        $response->assertSee('Gelmedi');
        // Gelmeyen cocuk icin "Kahvalti: Isaretlenmemis" anlamsiz gurultudur.
        $this->assertStringNotContainsString('Kahvaltı', $response->getContent());
        $this->assertStringNotContainsString('Uyku', $response->getContent());
    }

    public function test_gelmedi_olsa_da_gercek_kayit_gizlenmez(): void
    {
        // Celiskili veri: gelmedi isaretli ama yemek kaydi var. Gercek kaydi
        // veliden saklamak, celiskiyi gizlemekten daha kotudur.
        $this->record($this->defne, 'attendance', ['present' => false], '2026-08-23T06:10:00Z');
        $this->record($this->defne, 'meal', ['meal' => 'lunch', 'amount' => 'all'], '2026-08-23T09:00:00Z');

        $this->get('/v/'.$this->link())
            ->assertStatus(200)
            ->assertSee('Gelmedi')
            ->assertSee('Yedi');
    }

    public function test_yeni_baglanti_eskisini_iptal_eder(): void
    {
        $eski = $this->link();
        $this->get('/v/'.$eski)->assertStatus(200);

        // Komut tekrar calistirildi ya da gun yeniden gonderildi.
        $yeni = $this->link();

        $this->get('/v/'.$yeni)->assertStatus(200);
        // Eski adres artik acilmamali; yoksa her uretimde bir canli anahtar
        // daha birikir ve hicbiri geri alinamaz.
        $this->get('/v/'.$eski)->assertStatus(404);

        $this->assertSame(1, MagicLink::withoutGlobalScopes()->whereNull('revoked_at')->count());
        $this->assertSame(2, MagicLink::withoutGlobalScopes()->count());
    }

    public function test_komut_varsayilan_olarak_canli_baglantiyi_oldurmez(): void
    {
        // Veliye gonderilmis bir adres.
        $veliye = $this->link();

        // Hata ayiklamak icin komut calistirilir.
        $this->artisan('parent:links', [
            '--classroom' => $this->sinif->name,
            '--day' => $this->gun,
        ])->assertExitCode(0);

        // Velinin adresi YASAMALI; yoksa hata ayiklama araci yikici olur.
        $this->get('/v/'.$veliye)->assertStatus(200);
        $this->assertSame(1, MagicLink::withoutGlobalScopes()->whereNull('revoked_at')->count());
    }

    public function test_komut_rotate_ile_bilerek_yeniler(): void
    {
        $veliye = $this->link();

        $this->artisan('parent:links', [
            '--classroom' => $this->sinif->name,
            '--day' => $this->gun,
            '--rotate' => true,
        ])->assertExitCode(0);

        // Bilerek istendiginde eski adres olur.
        $this->get('/v/'.$veliye)->assertStatus(404);
        $this->assertSame(1, MagicLink::withoutGlobalScopes()->whereNull('revoked_at')->count());
    }

    public function test_komut_canli_baglanti_yoksa_uretir(): void
    {
        $this->assertSame(0, MagicLink::withoutGlobalScopes()->count());

        $this->artisan('parent:links', [
            '--classroom' => $this->sinif->name,
            '--day' => $this->gun,
        ])->assertExitCode(0);

        // Oldurulecek bir sey yoksa uretmek zararsizdir.
        $this->assertSame(1, MagicLink::withoutGlobalScopes()->whereNull('revoked_at')->count());
    }

    public function test_iptal_yalnizca_ayni_kapsami_etkiler(): void
    {
        $baskaGun = $this->link(null, '2026-08-22');
        $buGun = $this->link();

        // Farkli gun ayri kapsamdir, iptal edilmemeli.
        $this->get('/v/'.$baskaGun)->assertStatus(200);
        $this->get('/v/'.$buGun)->assertStatus(200);
    }

    public function test_baglanti_kullanildiginda_isaretlenir(): void
    {
        [$link, $token] = MagicLink::issue($this->veli, $this->defne, $this->gun);

        $this->assertNull($link->last_used_at);

        $this->get('/v/'.$token)->assertStatus(200);

        $this->assertNotNull($link->fresh()->last_used_at);
    }
}
