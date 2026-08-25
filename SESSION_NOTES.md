# SESSION_NOTES — kres-api

Bu dosya oturum kaybolursa bilginin gitmemesi için yazıldı. Eşi:
`C:\Work\kres-mobile\SESSION_NOTES.md` (mobil taraf).

Son güncelleme: 2026-08-24. Testler: **101/101 geçiyor**, 387 assertion.

---

## 1. NEREDE KALDIK

| İş | Durum |
|---|---|
| Auth (login/logout/me), sınıf ve çocuk listeleri | bitti |
| `POST records` + `records/batch` (idempotent yazma) | bitti |
| `DELETE records/{id}` (yumuşak silme) | bitti |
| Fotoğraf: imzalı yükleme + kesinleştirme + izin kontrolü | bitti |
| `photos:prune` (sahipsiz dosya temizliği) | bitti |
| `day-send` (günü gönder) + `day_sent_at`, `parent_count` | bitti |
| Veli verisi (`parents`, `child_parent`) | bitti |
| Sihirli link + veli sayfası (Blade) | bitti |
| Kuru çalışma: `LogChannel`, `parent:links` | bitti |
| `auth/session-log` (giriş kaydı, denetim izi) | bitti |
| Üçüncü öğün `snack` (ikindi) + veli sayfasında satırı | bitti |
| `day-send` yeniden gönderim (`resend: true`) | bitti |

**Açık işler:** yok. Mobil taraf da beklemede.

**Yapılmayanlar (bilerek):** gerçek SMS gönderimi (sağlayıcı hesabı yok),
veli tarafında mesajlaşma, aidat/duyuru/rapor, web panel, Docker/CI/deploy.

### Versiyonlama

**2026-08-24 itibarıyla ölçülen durum** (bu bölüm çabuk bayatlar; aşağıdaki
komutlarla tekrar ölçün):

Depo **git altında ve GitHub'a gönderilmiş durumda** — `git log origin/main..HEAD`
boş, çalışma ağacı temiz. Uzak depo:
`https://github.com/Ardahan0853/kres-api.git`.

```
git log origin/main..HEAD --oneline    # gönderilmemiş commit var mı
git status --short                     # kaydedilmemiş değişiklik var mı
```

```
073aa8a  sdaa
         day-send yeniden gönderim (resend): 9 dosya, +374 satır
9f30689  SESSION_NOTES.md'yi depoya al
5cdf0a9  Giriş kaydı ucu ve üçüncü öğün (ikindi)
         session_logs + snack: 11 dosya, +411 satır
ed28681  sad
         veli katmanı, magic link, veli sayfası, silme ucu: 34 dosya, +2095 satır
41643a2  Add generated columns for records.value
da8ecf0  Initial commit
```

Ara not: bu oturumun bir bölümünde depo git altında **değildi** — kullanıcı
`.git`'i bilerek sildirmişti, sonra yeniden kurup commit'lemiş. O dönemin
uyarıları artık geçersiz.

Bilinmesi gerekenler:

- **Bazı commit mesajları anlamsız** (`sad`, `sdaa`) ve bu düzenli değil:
  `5cdf0a9` ile `9f30689` gerekçeyi yazıyor, `073aa8a` yazmıyor. Yani commit
  geçmişine "neden" için güvenilemez; o bilgi bu belgede ve README'de durur.
  `073aa8a`'nın gerekçesi bölüm 3'teki "Yeniden gönderim" başlığındadır.
- **`SESSION_NOTES.md` (bu dosya) artık commit'leniyor.** `kres-mobile`
  tarafında hâlâ commit'lenmemiş olabilir; kontrol edin.
- `.env` izlenmiyor (doğru — `DEV_PARENT_PHONE` ve DB şifresi orada).

---

## 2. DEĞİŞMEZ KURALLAR

Bunlar tartışıldı ve karara bağlandı; değiştirmeden önce sor.

1. **Kurum izolasyonu.** `institution_id` asla URL'den veya gövdeden okunmaz;
   yalnızca `auth()->user()->institution_id`'den gelir
   (`App\Models\Concerns\BelongsToInstitution`).
2. **Kayıt id'sini istemci üretir** (UUIDv7). Aynı id ikinci kez geldiğinde
   yeni satır açılmaz.
3. **`recorded_at` / `taken_at` / `requested_at` sunucu saatiyle EZİLMEZ.**
   Olayın gerçek zamanı cihazda damgalanır. Sunucunun aldığı an ayrı tutulur
   (`created_at` / `sent_at`).
4. **Fotoğraf izni sunucuda da kontrol edilir.** Tek savunma hattı istemci
   değildir; izinsiz çocuk etiketlenemez ve veli sayfasında da yeniden bakılır.
5. **Veli sayfası tek çocuk içindir.** Başka çocuğun adı, sayısı, fotoğrafı
   asla görünmez.
6. **404 asla iş mantığı için kullanılmaz.** İstemci 404'ü "uç henüz yok" diye
   yorumlar. Bilinmeyen sınıf/çocuk 422 üretir. Tek istisna:
   `DELETE records/{id}` — orada 404 "zaten yok" demektir ve istemci başarı sayar.

---

## 3. MİMARİ KARARLAR VE GEREKÇELERİ

Kodun ne yaptığı okunur; **neden** öyle olduğu okunmaz. Bu bölüm onun için.

### Aynı id'ye yazma: "son gönderilen kazanır"

Başta "ilk kazanır"dı. Değiştirildi çünkü öğretmenin ikinci dokunuşu seçimi
değiştiriyor (12:05 "Yedi" → 12:15 "Az yedi") ve uyku kaydı aynı id'ye
`ended_at` eklenerek kapanıyor. İlk değeri korusaydık cihazda "Az yedi"
görünürken veliye "Yedi" giderdi.

Güncellemede yalnızca `value` ve `recorded_at` değişir; sınıf/çocuk/tür
sabittir (değiştiren gövde 422 alır).

### `recorded_at` guard'ı (gecikmiş istek koruması)

İstemcide zaman aşımına düşen bir istek ağda hâlâ yolda olabilir. Bu sırada
öğretmen düzeltirse, düzeltme sunucuya önce varır ve gecikmiş eski istek
üzerine yazardı. Bu yüzden **saklanandan kesin daha eski** `recorded_at`
taşıyan gövde yok sayılır (yanıt yine 200, `batch`'te `result: "stale"`).

**Eşitlik uygulanır** — istemcinin "5 sn geri al" akışı önceki değeri kendi
eski damgasıyla geri yazar; eşitlikte reddetseydik geri alma hiç işlemezdi.

### Yumuşak silme (`deleted_at`)

Sebep izlenebilirlik değil, **doğruluk**: silme ve yazma iki ayrı istektir ve
ağda sıraları bozulabilir. `DELETE` önce varıp 404 alır, ardından gecikmiş
`POST` gelirse **sert silmede kayıt geri gelirdi**. `deleted_at` doluysa `POST`
200 döner ama satırı diriltmez (`batch`'te `result: "deleted"`).

Gün gönderilmiş olsa bile silmeye izin verilir: veli sayfası canlı okuduğu için
düzeltme anında yansır. Kilitleseydik yanlış kayıt veliye kalıcı yanlış görünürdü.

### 422 gövdesinde `reason` alanı

Durum kodu tek başına yetmiyor. İstemci 422'yi kalıcı hata sayıp fotoğrafı
bırakıyordu; ama **dosya yok / boş / JPEG değil** durumları çoğunlukla yarım
kalmış bir PUT'tan çıkar ve doğru kurtarma vazgeçmek değil, **yeniden
yüklemektir**. Türkçe mesajı ayrıştırmak kırılgan olurdu, bu yüzden sabit kod:

| reason | anlam | istemci |
|---|---|---|
| `upload_incomplete` | dosya yok/boş/JPEG değil | 1. adımdan tekrar dene |
| `too_large` | 2 MB üstü | kalıcı |
| `key_mismatch` | anahtar bu fotoğrafa ait değil | kalıcı |
| `consent_blocked` | izinsiz çocuk (+ `blocked_child_ids`) | kalıcı |
| `not_in_classroom` | çocuk bu sınıfa ait değil | kalıcı |

### `records/batch` her zaman 200 döner

Zarf geçerliyse HTTP 200'dür, sonuç **kayıt başına** `results` dizisindedir.
Tek bozuk kayıt yüzünden tüm gövdeye 422 dönseydik kuyruktaki diğer 49 sağlam
kayıt da reddedilir ve sonsuza kadar tekrar denenirdi. Doğrulama kayıt başına.

### Sihirli link

- **Kapsam: veli + çocuk + GÜN.** Gün olmasaydı akşam gönderilen link ertesi
  sabah boş sayfa gösterir ve süresi bitene kadar öyle kalırdı.
- **Ham token saklanmaz**, yalnızca sha256 özeti. DB sızarsa eldeki özetlerden
  çalışan link üretilemez. **Bunun sonucu:** "mevcut linki tekrar göster"
  mümkün değil — mobil taraf bunu istedi, yapılamadığı açıklandı.
- **Kapsam başına tek canlı link.** Yeni üretilince eskisi `revoked_at` ile
  iptal edilir (satır silinmez, iz kalır). Aksi halde her çalıştırmada bir
  anahtar daha birikir ve hiçbiri geri alınamaz.
- **`parent:links` varsayılan olarak ÜRETMEZ.** Üretim mevcut linki iptal
  ettiği için, hata ayıklamak üzere çalıştırılan komut velinin elindeki adresi
  sessizce öldürürdü. Üretim `--rotate` ile açıkça istenir.

### `parent_count` DISTINCT sayılır

Bir velinin sınıfta iki çocuğu (kardeşler), bir çocuğun iki velisi olabilir.
Çocuk sayısından türetilemez.

Veli tablosu yokken bu alan **null** dönüyordu — uydurma sayı yerine null,
çünkü "18 veliye gönderildi" demek öğretmene yalan söylemek olurdu.

### Yeniden gönderim: açık bayrak, çıkarım değil

Önce bir sınıfın bir günü **tek kez** gönderilebiliyordu (`unique(classroom_id,
day)`) ve her tekrar 200 dönüp bildirim üretmiyordu. Gerekçe doğruydu ama
dardı: kuyruğun tekrarı ile **öğretmenin ikinci niyeti** aynı sepete
düşüyordu. Sabah erken gönderilen günün öğleden sonraki kayıtları veliye bir
daha bildirilemiyordu.

Not: kilitlenen **veri değildi**. Bağlantı 7 gün canlı (`MagicLink::
LIFETIME_DAYS`) ve veli sayfası kayıtları görüntüleme anında okuyor — sabah
gönderilen adres akşam açıldığında günün tamamını gösteriyor. Çıkmayan tek
şey ikinci haberdi. Kullanıcıya "gün kilitleniyor" diye sunulmadı.

Artık `resend: true` ile yeniden gönderilebiliyor. **Çıkarım yapılmaz**
("yeni id geldiyse öğretmen tekrar basmıştır" deseydik, yanıt ağda kaybolup
kuyruk yeni id ile denediğinde veli ikinci SMS'i alırdı). Bayrak, kuyruğun
tekrarı ile ikinci niyeti ayıran tek güvenilir işaret. Mobil taraf da bunu
böyle önerdi ve idempotenslik anahtarının `id` kalması onların şartıydı.

Şema: tekil kısıt `(classroom_id, day, attempt)` oldu, her kabul edilen
gönderim kendi satırı. `attempt`'i sunucu hesaplar ve kısıt yarışı önler —
**satır kilidi bilerek seçilmedi**, çünkü `lockForUpdate` SQLite'ta yok
sayılır ve testlerin göremediği bir motor farkı daha eklerdi.

Bedeli bilerek kabul edildi: yeniden gönderim bağlantıları döndürür, velinin
elindeki eski adres ölür (ham token saklanmadığı için "eskisini tekrar
gönder" yok).

### Kayıt ucunda 422'nin geçici hâli yok

Mobil taraf "422 alan kayıt sonsuza kadar kuyrukta dönüyor" diye sordu ve
kalıcı/geçici ayrımı isteyip istemediğimizi sordu. `RecordController` ve
`StoreRecordRequest`'teki bütün çıkışlar sayıldı: **dokuz tane var, dokuzu da
kalıcı** (gövde şekli 4, bilinmeyen sınıf/çocuk 2, değişmezlik ihlali 3).

Sebep basit: kuyruktaki kaydın gövdesi bir daha değişmez, aynı gövde bin kez
de gitse aynı cevabı alır. Fotoğraftaki `upload_incomplete`'in geçici olması
yükleme ayrı bir adım olduğu içindi; kayıt yazma tek adım.

`reason` kodları (`invalid_body`, `unknown_classroom`, `not_in_classroom`,
`immutable_record`) önerildi ama **bir hata düzeltmiyor** — bugün ayrım zaten
"hepsi kalıcı". Yalnızca öğretmene gösterilen gerekçeyi düzeltir. Kullanıcı
kararı bekleniyor. Eklenirse sözleşme maddesi şart: `reason` yoksa ya da
tanınmıyorsa 422 KALICI sayılır, yoksa ileride geçici bir kod eklendiğinde
eski istemciler yanlış tarafa düşer.

### `records.*` kuralı zarftan çıkarıldı

`records/batch` zarfında `'records.*' => ['array']` kuralı vardı ve nesne
olmayan tek bir eleman tüm isteği 422 yapıyordu — yani zarfın kendi
açıklamasının ("tek bozuk kayıt yüzünden diğer 49'u reddetme") tam olarak
engellemek istediği şey. Kural çıkarıldı, kontrol döngünün içine alındı;
bozuk eleman artık kendi satırında `invalid` dönüyor.

Zarf düzeyinde kalan tek sınır kayıt sayısı (200). O 422 bilerek duruyor:
tekrar denenerek değil, listeyi bölerek geçilir — aynı zarf bin kez de gitse
aynı cevabı alır.

**Ölçüldü: bugün bu sınıra dayanılmıyor.** Mobil tarafta `BATCH_SIZE = 50` ve
bu sayı doğrudan SQL `LIMIT`'ine gidiyor, yani 5000 kayıtlık birikim 100 ayrı
istekte çıkıyor. Sınır aşılamadığı için mobil taraf zarf 422'sini kalıcı
saymadı (ulaşılamayan dalı sertleştirmek 50 geçerli kaydı birden engelleme
riski taşıyordu). Biri o sabiti yükseltmek isterse gerekçe mobil taraftaki
sabitin başında yazılı.

### Boş gün sunucuda reddedilmedi

Mobil taraf "hiç kayıt yokken gönderimi sunucu da reddetsin mi" diye sordu.
Reddedilmedi. Belirleyici sebep: kayıtlar ve **fotoğraflar ayrı kuyruklarda**
ve `photoCount` gönderim anında sayılıyor — sadece fotoğraf çekilmiş gerçek
bir gün, yüklemeler bitmeden gönderilirse sunucuda boş görünür. 422 istemcide
kalıcı ret olduğundan yanlış pozitifin bedeli ölü gün olurdu. Boş gün,
fotoğraf izni gibi bir güvenlik sınırı değil (kural 4 ile karıştırılmasın).

### Üçüncü öğün: `snack` (ikindi)

Başta öğün yalnızca `breakfast` ve `lunch`'tı; README'de "`snack` yoktur"
yazıyordu. Kreste ikindi kahvaltısı da veriliyor ve veli onu da soruyor,
bu yüzden üçüncü değer eklendi. **İki tarafı bağlayan bir değişikliktir**
(mobil artık `meal: "snack"` gönderebiliyor).

Şema değişmedi: `value` serbest `jsonb` ve `meal_kind` generated column'ı
`value->>'meal'`'i olduğu gibi çıkarıyor — migration gerekmedi. Doğrulanan tek
şey `type`, o da `meal` olarak zaten geçerliydi.

Veli sayfasında ikindi **kaydı yoksa da satır görünür** ("İşaretlenmemiş"),
diğer öğünlerle aynı davranış: satırın hiç olmaması "ikindi verilmedi" ile
"işaretlenmedi"yi ayırt edilemez kılardı.

### `records.value` serbest `jsonb` + türetilmiş kolonlar

Şekiller adım adım netleştiği için `value` serbest bırakıldı ve tip başına katı
doğrulama konmadı (yeni bir alan adı, kuyrukta sonsuz 422 döngüsü demek olurdu).
`type` ise sabit listeye göre doğrulanır.

Web panelinin `value->>'amount'` yazmak zorunda kalmaması için generated
column'lar eklendi: `present`, `meal_kind`, `meal_amount`, `nap_started_at`,
`nap_ended_at`, `toilet_kind`. Yazma yolu değişmez; kolonlara **yazılamaz**
(DB reddeder), yani `value` ile ayrışamazlar.

### `session_logs` bir kanıt değil, beyandır

Çevrimdışı giriş cihazda doğrulanır; sunucu o anı görmez. `started_at` ve
`offline` istemcinin bildirdiği değerlerdir ve doğrulanamazlar. Tablo denetim
izi olarak okunur, kritik bir karara dayanak yapılamaz — bu sınır kodda ve
README'de açıkça yazılı.

Aynı id başka bir öğretmene aitse 409 döner ve kayıt gösterilmez; dönmek onun
giriş saatini sızdırırdı. `offline` alanı bilerek gevşek doğrulanır (gelmezse
`false`), çünkü 422'ye takılan istek istemcinin kuyruğunda sonsuza kadar döner.

### `Guardian` sınıf adı sapması

Tablo `parents`, model **`Guardian`**. PHP'de `Parent` ayrılmış kelimedir ve
sınıf adı olamaz (denendi, fatal error). Tablo adı sözleşmeye sadık bırakıldı.

### Bildirim kanalı arayüz arkasında

`ParentLinkChannel` → şu an tek uygulama `LogChannel` (SMS göndermez, log'a
yazar, telefon maskeli). Sağlayıcı gelince yeni sınıf + `config/kres.php`
değişikliği yeterli; `day-send` koduna dokunulmaz.

Linkler **API yanıtında dönmez** — öğretmenin telefonunda veli linki tutmanın
faydası yok, sızma yüzeyi artar.

---

## 4. ŞEMA

Migration sırası (`database/migrations/`):

```
0001_01_01_000000  institutions, users
0001_01_01_00000x  cache, jobs, classrooms, children, classroom_user
2026_08_23_155817  personal_access_tokens        (uuidMorphs — tokenable uuid)
2026_08_23_200000  records
2026_08_23_210000  children.photo_consent        (+ mevcut satırlara dağıtım)
2026_08_23_220000  photos, child_photo
2026_08_23_230000  day_sends
2026_08_23_235000  records generated columns
2026_08_24_000000  parents, child_parent
2026_08_24_001000  day_sends.parent_count
2026_08_24_010000  magic_links
2026_08_24_020000  records.deleted_at
2026_08_24_030000  magic_links.revoked_at        (+ eski linkleri iptal)
2026_08_24_040000  session_logs
2026_08_24_050000  day_sends.attempt/resend     (tekil kısıt attempt'e taşındı)
```

Tüm alan tabloları **UUID** birincil anahtar kullanır.

İki migration **veri de taşır** (bilerek): `photo_consent` dağıtımı ve
`revoked_at` temizliği. Sebep: `migrate:fresh` istemcideki id'leri ve
oturumları düşürüyor, o yüzden veri bozulmadan yerinde düzeltildi.

---

## 5. API SÖZLEŞMESİ

| Metot | Yol | Not |
|---|---|---|
| POST | `api/v1/auth/login` | `{token, user}` |
| POST | `api/v1/auth/logout` | yalnızca o token → 204 |
| GET | `api/v1/me` | `{data:{...}}` |
| POST | `api/v1/auth/session-log` | giriş kaydı, 201 / 200, 409 başka kullanıcı |
| GET | `api/v1/classrooms` | `children_count`, `parent_count`, `day_sent_at` |
| GET | `api/v1/classrooms/{classroom}/children` | `photo_consent` içerir |
| POST | `api/v1/records` | 201 yeni / 200 güncellendi |
| POST | `api/v1/records/batch` | daima 200, kayıt başına sonuç |
| DELETE | `api/v1/records/{id}` | 204 / 404 (zaten yok) |
| POST | `api/v1/photos/upload-url` | imzalı PUT adresi, 10 dk |
| POST | `api/v1/photos` | 201 / 200 |
| POST | `api/v1/classrooms/{classroom}/day-send` | 201 / 200, `resend: true` → 201 |
| GET | `/v/{token}` | veli sayfası, public, `api/v1` altında değil |

**İstemcinin bağımlı olduğu davranışlar** (değiştirmeden önce mobil tarafa sor):

- 2xx ve 409 → başarı; 401 → oturum düşer; 403 ve 409 → kalıcı ret;
  422 → kalıcı (fotoğrafta `reason` ile ayrışır); 5xx → tekrar denenir.
- `batch` yanıtında `id` + `index` döner; id doğrulanamazsa null gelir,
  eşleşme `index` ile yapılır.
- Aynı gün ikinci kez `day-send` → **409 değil 200** (gün gitti, "gönderilemedi"
  demek yanlış bilgi olur). Bildirim yalnızca `resend: true` ile yeniden çıkar.
- `GET classrooms` → `day_sent_at`, günün **en son** gönderiminin damgasıdır
  (yeniden gönderimden sonra o saat görünür).
- `upload_url`'in host'u **isteğin host'undan** üretilir, `APP_URL`'den değil.

---

## 6. YAŞANMIŞ TUZAKLAR

Hepsi gerçekten yaşandı ve ölçülerek bulundu. Tekrar ederse zaman kaybetmeyin.

### `artisan serve` TEMP/TMP geçirmiyor → 8 KB üstü her PUT 500

`ServeCommand::$passthroughVariables` bir izin listesidir ve `TEMP`/`TMP` onda
yoktur. Windows'ta PHP yazılabilir geçici dizin bulamayınca, gövdesi geçici
dosyaya alınması gereken her istek `Unable to create temporary file` ile
**500** döner. Ölçülen eşik: 8 KB geçiyor, 16 KB düşüyor — yani her gerçek
fotoğraf. `AppServiceProvider::boot()` bu ikisini listeye ekler.
**Düzeltme sunucu yeniden başlatılmadan etkili olmaz.**

### Portu tutan eski süreç, yenisini sessizce düşürüyor

Yeni `artisan serve` başlatmak yetmez; eski süreç portu bıraktığı sürece yeni
süreç bağlanamaz ve **sessizce ölür**. İki kez "yeniden başlattım" denip
düzelmemesinin sebebi buydu. Önce tüm `php.exe` süreçleri durdurulmalı:

```
Get-NetTCPConnection -LocalPort 8000 -State Listen   # portu kim tutuyor
Stop-Process -Id <pid> -Force
php artisan serve --host=0.0.0.0
```

### `withoutGlobalScopes()` yumuşak silmeyi de kaldırır

Veli sayfası kayıtları böyle okuyordu; öğretmenin sildiği kayıt velide
görünmeye devam edecekti. Doğrusu `withoutGlobalScope('institution')`.

### PostgreSQL/SQLite farkları (testler SQLite, üretim Postgres)

1. **`recorded_at` 3 saat kayıyordu.** Laravel timestamp'i offset'siz yazar;
   Postgres oturum dilimi UTC değilse `timestamptz` kolonlara yazılan saatler
   kayar. `config/database.php` içinde `'timezone' => 'UTC'` ile sabitlendi.
   *Testler bunu yakalayamaz* — SQLite'ta bu davranış yok.
2. **`date` kolonunda düz eşitlik.** `where('day', '2026-08-23')` Postgres'te
   çalışıp SQLite'ta boş dönüyordu. Her yerde `whereDate` kullanılır.
3. **JSON boolean.** `->>` Postgres'te `'true'/'false'`, SQLite'ta `'1'/'0'`
   döndürür. Generated column ifadeleri motora göre ayrıldı; `present` için
   modelde `'boolean'` cast'i var.
4. **Postgres `text→timestamptz` cast'ini generated column'da kabul etmez**
   (immutable değil). Uyku zamanları bu yüzden `text` (ISO 8601 sözlük
   sırasında da kronolojiktir).
5. **`id` kolonu uuid**; UUID olmayan metinle karşılaştırmak Postgres'te hata
   verir (`parent:links --classroom=Papatyalar` bu yüzden patlamıştı).

### Veli sayfasında saatler UTC gösteriliyordu

`app.timezone` UTC olduğu için 23:47'de girilen kayıt veliye **20:47** olarak
görünüyordu. `kres.display_timezone` (varsayılan `Europe/Istanbul`) eklendi.

### Aynı türde İLK kayıt gösteriliyordu

Öğretmen "geldi"yi "gelmedi"ye düzelttiğinde veli eski bilgiyi görüyordu.
Artık **sonuncusu** geçerli.

### Carbon 3 `diffInMinutes` ondalık döner

Sayfada "0.0625 dk" yazıyordu. Açıkça yuvarlanıyor.

### İmzalı URL yolu gizlemez

"`storage_key`'i sayfaya yazma" istendi ama imzalı adres zaten yolu içerir.
Önemli olan yolun tek başına işe yaramaması: imzasız istek **403** alır
(ölçüldü) ve imza kısa sürede geçersizleşir.

### Yükleme rotası yalnızca imzayı doğrular

Laravel'in `ReceiveFile`'ı içerik türüne ve boyuta **bakmaz**. Gerçek denetim
kesinleştirmededir: diskteki asıl boyut + JPEG magic byte.

### `admin` ile giriş yapılamıyordu

Sebep kod değil veriydi: DB'deki hash `password`'ün hash'i değildi (muhtemelen
cast eklenmeden önce çift hash'lenmiş). `migrate:fresh --seed` çözdü.

---

## 7. ORTAM

```
PHP     C:\Users\ardah\.config\herd\bin\php84\php.exe   (8.4.16, Herd)
Postgres 127.0.0.1:5432 / kres_dev / postgres:postgres  (taşınabilir kurulum)
        C:\Work\pgsql\start-pg.cmd  ile elle başlatılır
Sunucu  php artisan serve --host=0.0.0.0    (--host şart, telefon için)
```

```
php artisan migrate                      # additive, oturumları düşürmez
php artisan migrate:fresh --seed         # DİKKAT: tüm id'ler değişir, oturumlar düşer
php artisan db:seed --class=DevParentSeeder
php artisan test
./vendor/bin/pint --test <yol>
php artisan photos:prune --hours=48 [--dry-run]
php artisan parent:links --classroom=Papatyalar --day=2026-08-23 \
    --url=http://192.168.1.2:8000 [--rotate]
```

**Test hesapları** (hepsi `password`): `admin@papatya.test`,
`ayse@papatya.test` (Papatyalar+Laleler), `mehmet@papatya.test` (Menekşeler).
İzolasyon için ikinci kurum: "Test Kurum 2" / Kelebekler.

**Test velisi** `.env` içindeki `DEV_PARENT_PHONE`'dan gelir (numara koda ve
depoya yazılmaz; env boşsa veli hiç oluşturulmaz). Papatyalar'daki izinli ilk
çocuğa bağlanır — isim rastgele üretildiği için isme göre aranmaz.

**Pint notu:** `app/Http/Resources/*` ve eski
`create_personal_access_tokens_table` migration'ı Pint'in
`fully_qualified_strict_types` kuralına takılır. Bu **repoda zaten var olan**
konvansiyondur, yeni dosyalarda düzeltildi; eskilere dokunulmadı.

---

## 8. İKİ OTURUM DÜZENİ

İki Claude oturumu var: bu repo (`kres-api`) ve `kres-mobile`. Kullanıcının
belirlediği düzen:

- **Repo içi kararlar** (şema, seeder, kod yapısı, Laravel tercihleri)
  → doğrudan kullanıcıya sorulur.
- **İki tarafı bağlayan kararlar** (HTTP kodları, alan adları, yanıt şekilleri,
  `type` listesi) → mobil oturumla konuşulur; o kullanıcıya tek yerden götürür.
- Raporlar mobil oturuma yazılır, o özetler.

Karşılıklı veri hijyeni: her iki taraf da kendi test satırlarını siler ve
diğerinin verisine dokunmaz. Beklenmedik satır görülürse sorulur.

`migrate:fresh` mobil tarafı **doğrudan etkiler** (oturumlar düşer, id'ler
değişir) — koşmadan önce haber verilir.
