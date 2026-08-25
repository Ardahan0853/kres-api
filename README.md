# kres-api

Kreş & anaokulu veli iletişim SaaS'ının backend'i. Laravel 13 + PostgreSQL + Sanctum.

Bu depo `adim-04-api-mobil-baglantisi.md` dosyasının **Bölüm A** kapsamındadır.
Mobil uygulama ayrı depoda: `kres-mobile`.

## Çalıştırma

PostgreSQL bu makinede taşınabilir kurulumdur ve Windows servisi olarak
kayıtlı değildir; bilgisayarı her yeniden başlattığında elle açman gerekir:

```
C:\Work\pgsql\start-pg.cmd     # durdurmak icin stop-pg.cmd
```

Sonra API:

```
php artisan serve --host=0.0.0.0
```

`--host=0.0.0.0` şart. Aksi halde sunucu yalnızca 127.0.0.1'i dinler ve
telefon/emülatör `192.168.1.2:8000` adresine bağlanamaz.

> **Sunucuyu değiştirdikten sonra yeniden başlat.** `artisan serve` alt
> sürece yalnızca izin listesindeki ortam değişkenlerini geçirir ve
> `TEMP`/`TMP` o listede yoktur. Windows'ta PHP bu ikisi olmadan yazılabilir
> geçici dizin bulamaz; geçici dosyaya alınması gereken her istek gövdesi
> (~8 KB üstü, yani her gerçek fotoğraf) `Unable to create temporary file`
> ile **500** döner. `AppServiceProvider::boot()` bu ikisini listeye ekler,
> ama düzeltme ancak sunucu yeniden başlatıldığında etkili olur.

Veritabanını sıfırlayıp yeniden doldurmak için:

```
php artisan migrate:fresh --seed
```

## Veritabanı

| | |
|---|---|
| Sunucu | 127.0.0.1:5432 |
| Veritabanı | `kres_dev` |
| Kullanıcı | `postgres` / `postgres` |

Tüm alan tabloları UUID birincil anahtar kullanır. Sanctum'un
`personal_access_tokens.tokenable_id` kolonu da bu yüzden `uuidMorphs` ile
tanımlandı.

## Test hesapları

Hepsinin şifresi `password`.

| E-posta | Rol | Sınıflar |
|---|---|---|
| `admin@papatya.test` | admin | (kurumun tamamını görür) |
| `ayse@papatya.test` | teacher | Papatyalar, Laleler |
| `mehmet@papatya.test` | teacher | Menekşeler |

Ayrıca izolasyon testi için ikinci bir kurum vardır: **Test Kurum 2**
(Kelebekler sınıfı, 5 çocuk). Papatya kullanıcılarının hiçbir uç noktada
bu veriyi görmemesi gerekir.

## Uç noktalar

Hepsi `/api/v1` altında. `auth/login` dışındakiler Sanctum korumalı.

| Metot | Yol | Açıklama |
|---|---|---|
| POST | `auth/login` | `email`, `password`, `device_name` → `{token, user}` |
| POST | `auth/logout` | Yalnızca o isteğin token'ını siler → 204 |
| GET | `me` | `{data: {...user}}` |
| POST | `auth/session-log` | Giriş kaydı (denetim izi). 201 yeni / 200 zaten var |
| GET | `classrooms` | Öğretmenin atandığı sınıflar; admin ise kurumun tamamı. `day_sent_at` içerir |
| POST | `classrooms/{classroom}/day-send` | Sınıfın gününü gönderir. `resend: true` ile yeniden gönderir |
| GET | `classrooms/{classroom}/children` | Atanmamış öğretmene 403, `photo_consent` içerir |
| POST | `records` | Günlük kayıt yazar. Idempotent, aşağıya bak |
| POST | `records/batch` | `{records: [...]}`, en fazla 200 kayıt, her zaman 200 döner |
| DELETE | `records/{id}` | Kaydı siler (yumuşak). Yok ise 404 |
| POST | `photos/upload-url` | Kısa ömürlü imzalı yükleme adresi |
| POST | `photos` | Yüklenen fotoğrafı kesinleştirir |

## Kayıtlar

Mobil uygulama çevrimdışı çalışır: öğretmenin girdiği kayıt önce cihazdaki
SQLite kuyruğuna yazılır, ağ geldiğinde gönderilir. Bu iki sonucu doğurur.

**Kayıt id'sini istemci üretir (UUIDv7).** Kötü bağlantıda zaman aşımına düşen
bir istek aslında sunucuya ulaşmış olabilir, kuyruk da onu tekrar gönderir.
Bu yüzden yazma idempotenttir: aynı id ikinci kez geldiğinde yeni satır
açılmaz, mevcut kayıt güncellenir ve 200 döner. **Kazanan son gönderimdir.**

Bu bir tercih değil, ürünün akışı: öğretmenin ikinci dokunuşu seçimini
değiştirir (12:05'te "Yedi", 12:15'te "Az yedi") ve uyku kaydı aynı id'ye
`ended_at` eklenerek kapanır. İlk değeri korusaydık cihazda "Az yedi"
görünürken veliye "Yedi" giderdi.

Güncellemede yalnızca `value` ve `recorded_at` değişir. Kaydın sınıfı,
çocuğu ve türü sabittir; bunları değiştiren bir gövde 422 alır. `created_at`
ilk yazma anında kalır, `updated_at` tazelenir.

**Gecikmiş istek koruması.** İstemcide zaman aşımına düşen bir istek ağda
hâlâ yolda olabilir. Bu sırada öğretmen seçimini değiştirirse düzeltme
sunucuya önce varır ve gecikmiş eski istek onun üzerine yazabilirdi. Bu
yüzden saklanandan **kesin daha eski** `recorded_at` taşıyan gövde yok
sayılır; yanıt yine 200'dür (4xx dönseydik istemci o kaydı sonsuza kadar
tekrar denerdi), `batch` içinde sonucu `stale` görünür.

Eşit `recorded_at` **uygulanır**. İstemcinin "5 sn geri al" akışı önceki
değeri kendi eski damgasıyla geri yazar; eşitlikte reddetseydik geri alma
sunucuya hiç işlemezdi.

**`recorded_at` olayın gerçek zamanıdır, sunucu saati değildir.** Öğretmen
09:12'de yoklama alıp 14:00'te ağa kavuşabilir; veliye 09:12 görünmelidir.
Sunucunun kaydı teslim aldığı an ayrıca `created_at` içinde tutulur.

Durum kodları istemcinin kuyruk davranışını doğrudan belirler:

| Kod | Anlamı | İstemci ne yapar |
|---|---|---|
| 201 | Yeni kayıt yazıldı | `synced` |
| 200 | Kayıt zaten vardı, güncellendi | `synced` |
| 401 | Token geçersiz | Kuyruk susar, giriş ekranı |
| 403 | Öğretmen o sınıfa atanmamış | Kalıcı hata, tekrar denemez |
| 409 | id başka kuruma ait | Kalıcı hata |
| 422 | Gövde hatalı (tür, sınıf, çocuk) | Artan beklemeyle tekrar dener |

Bu uç **hiçbir zaman 404 dönmez**. İstemci 404'ü "uç henüz yok" olarak
yorumlayıp kaydı kuyrukta bekletir; bu yüzden bilinmeyen sınıf veya çocuk
404 değil 422 üretir.

`records/batch` zarf geçerliyse her zaman 200 döner ve kayıt başına sonuç
listeler. Tek bozuk kayıt yüzünden 422 dönmek kuyruktaki diğer 49 sağlam
kaydı da reddedeceği için, doğrulama kayıt başına yapılır. İstemci HTTP
koduna değil, kendi id'sinin satırına bakmalıdır:

```json
{"results": [
  {"id": "...", "index": 0, "status": 201, "result": "created"},
  {"id": "...", "index": 1, "status": 200, "result": "duplicate"},
  {"id": "...", "index": 2, "status": 422, "result": "invalid", "message": "..."}
]}
```

Nesne olmayan bir eleman da **kendi satırında** reddedilir (`id` doğrulanamadığı
için `null` gelir, eşleşme `index` ile yapılır). Önce bu kural zarftaydı ve
bozuk tek bir kuyruk satırı tüm isteği 422 yapıyordu — yani yukarıdaki
gerekçenin tam olarak engellemek istediği şey.

Zarf düzeyinde kalan tek sınır kayıt **sayısıdır**: 200 üstü liste 422 alır.
Bu 422 tekrar denenerek değil, **listeyi bölerek** geçilir — aynı zarf bin kez
de gitse aynı cevabı alır. İstemci kuyruğu 200'lük parçalara bölmelidir.

### 422 kalıcıdır

Kayıt ucunda 422'nin geçici hâli **yoktur**; dokuz çıkışın dokuzu da kalıcıdır
(gövde şekli, bilinmeyen sınıf/çocuk, kaydın sınıf/çocuk/tür değişmezliği).
Aynı gövde tekrar gönderilerek düzelen bir 422 yoktur, çünkü kuyruktaki kaydın
gövdesi bir daha değişmez.

Fotoğraf ucundaki `upload_incomplete`'in geçici olmasının sebebi burada yoktur:
orada yükleme ayrı bir adımdır ve istemci 1. adımdan tekrar deneyerek durumu
düzeltebilir. Kayıt yazma tek adımdır.

> İstemci kayıt 422'sini **kalıcı ret** saymalıdır. Sonsuza kadar tekrar
> denemek düzelmeyecek bir hatayı sessizce döndürmek olur.

### Kayıt silme

Öğretmen 5 saniyelik geri alma penceresi kaçtıktan sonra da yanlış girdiği
kaydı silebilir: `DELETE records/{id}` → **204**. Kayıt yoksa veya zaten
silinmişse **404** döner; istemci bunu başarı sayar, çünkü kuyruk aynı silme
isteğini tekrar gönderebilir.

Silme **yumuşaktır** (`deleted_at`). Sebebi izlenebilirlik değil, doğruluk:
silme ve yazma iki ayrı istektir ve ağda sıraları bozulabilir. `DELETE` önce
varıp 404 alır, ardından gecikmiş `POST` gelirse sert silmede kayıt geri
gelirdi. `deleted_at` doluysa `POST` **200** döner ama satırı diriltmez
(`batch` içinde sonucu `deleted` görünür).

Gün gönderilmiş olsa bile silmeye izin verilir: veli sayfası canlı okuduğu
için düzeltme anında yansır. Kilitleseydik yanlış kayıt veliye kalıcı olarak
yanlış görünürdü.

> Veli sayfası kayıtları `withoutGlobalScope('institution')` ile okur.
> `withoutGlobalScopes()` deseydik yumuşak silme kapsamı da kalkar ve silinen
> kayıt velide görünmeye devam ederdi.

### Türetilmiş kolonlar

Web panelinin `value->>'amount'` yazmak zorunda kalmaması için `value`
içindeki alanlar **generated column** olarak da açıldı:

| kolon | kaynak | tip |
|---|---|---|
| `present` | `value->>'present'` | boolean |
| `meal_kind` | `value->>'meal'` | text |
| `meal_amount` | `value->>'amount'` | text |
| `nap_started_at` | `value->>'started_at'` | text (ISO 8601) |
| `nap_ended_at` | `value->>'ended_at'` | text (ISO 8601) |
| `toilet_kind` | `value->>'kind'` | text |

Gerçek kolon gibi sorgulanır ve indekslenebilir:

```sql
select * from records where type = 'meal' and meal_amount = 'all';
select count(*) filter (where present) from records where type = 'attendance';
```

Yazma yolu değişmez: kolonlar `value`'dan türetilir, uygulama onlara yazmaz
ve **yazamaz** — doğrudan `update` denemesini veritabanı reddeder, böylece
iki kaynak ayrışamaz. Şekil değişirse kolon düşürülüp yeniden tanımlanır,
`value` olduğu gibi kaldığı için veri kaybolmaz.

> İfadeler motora göre ayrılır: PostgreSQL'de `->>` bir JSON boolean için
> `'true'/'false'`, SQLite'ta `'1'/'0'` döner. `present` iki motorda da
> boolean gibi karşılaştırılabilsin diye ayrı yazıldı; bu davranış testle
> korunuyor. Zaman alanları metin bırakıldı çünkü PostgreSQL türetilmiş
> kolonda `text -> timestamptz` cast'ini kabul etmiyor (immutable değil);
> ISO 8601 damgaları sözlük sırasında da kronolojik sıralanır.

`value` alanı tip başına değişen serbest JSON'dur (`jsonb`) ve **bilerek
doğrulanmaz**. Şekiller sonraki adımlarda genişleyeceği için katı doğrulama
istemci ile sunucu arasında sürüm uyumsuzluğu üretir. `type` ise sabit
listeye göre doğrulanır: `attendance`, `meal`, `nap`, `toilet`, `note`,
`photo`. Listeye yeni tür eklemek backend değişikliği gerektirir.

Mobil istemcinin gönderdiği şekiller (sözleşme belgesi, doğrulanmaz):

```
attendance  {"present": true}                                  false = gelmedi
meal        {"meal": "breakfast"|"lunch"|"snack",
             "amount": "all"|"some"|"none"}                    all=Yedi, some=Az yedi, none=Yemedi
nap         {"started_at": "ISO", "ended_at": "ISO"|null}
toilet      {"kind": "toilet"|"diaper"}
```

- Üç öğün vardır: `breakfast` (kahvaltı), `lunch` (öğle), `snack` (**ikindi**).
  Veli sayfası üçünü de ayrı satırda gösterir.
- Uyku **tek kayıttır**: "Uyudu" kaydı açar, "Uyandı" aynı id'ye `ended_at`
  ekler. Çocuk ikinci kez uyursa yeni id ile yeni kayıt açılır.
- Tuvalet/bez her dokunuşta ayrı kayıttır; günlük sayaç istemcide üretilir.
- Yoklamada `present: false` de bir kayıttır. Kaydın yokluğu "işaretlenmedi"
  demektir; üçü farklı durumdur.

Kayıtlar istemcide 5 saniye bekletilerek gönderilir ("geri al" penceresi),
bu yüzden art arda yapılan işaretlemeler genelde tek `batch` isteğiyle gelir.

> PostgreSQL bağlantısı `config/database.php` içinde `'timezone' => 'UTC'`
> ile sabitlenmiştir. Laravel timestamp'i offset'siz yazdığı için, oturum
> saat dilimi UTC değilse `timestamptz` kolonlara yazılan saatler makinenin
> yerel dilimine göre kayar (bu makinede 3 saat). `recorded_at` için bu kayma
> kabul edilemez.

## Giriş kaydı

Mobil uygulamada çevrimdışı giriş var: ağ yokken öğretmen, cihazda saklanan
parola özetiyle içeri girebiliyor. Bu giriş sunucudan geçmediği için bağlantı
gelince ayrıca bildirilir.

```
POST auth/session-log   {"id": "<uuid v7>", "started_at": "ISO", "offline": true}
```

`started_at` girişin **yapıldığı** andır: 09:00'da çevrimdışı giren öğretmen
11:00'da bağlansa da 09:00 görünür. Sunucunun kaydı aldığı an `created_at`'te
ayrıca durur. `id` istemciden gelir ve idempotency anahtarıdır.

Aynı id başka bir öğretmene aitse **409** döner ve kayıt gösterilmez —
dönmek onun giriş saatini sızdırırdı.

> **Bu tablo kanıt değildir.** `started_at` ve `offline` istemcinin beyanıdır;
> çevrimdışı giriş sunucudan geçmediği için doğrulanabilecek bir şey yoktur.
> Denetim izi olarak okunabilir, kritik bir karara dayanak yapılamaz.

`offline` alanı bilerek gevşek doğrulanır (gelmezse `false`): katı bir kural
yüzünden 422'ye takılan istek istemcinin kuyruğunda sonsuza kadar dönerdi.

## Fotoğraf izni

`children.photo_consent` üç değer alır: `granted`, `denied`, `pending`.
Varsayılan `pending`'dir — izin alınmamış saymak güvenli olandır. Alan
`GET /classrooms/{classroom}/children` yanıtında döner.

Yalnızca `granted` olan çocuk fotoğrafta etiketlenebilir. Bu kural sunucuda
da uygulanır; tek savunma hattı istemci değildir.

Seeder her sınıfta **en az bir `denied` ve bir `pending`** bırakır
(`Child::consentForIndex()`), aksi halde izin akışı gerçekten denenemez.
Aynı kural, alanı ekleyen migration'da mevcut satırlar için de uygulanır:
`migrate:fresh` istemcideki id'leri ve oturumları düşürdüğü için veri
bozulmadan yerinde dağıtılır.

## Fotoğraflar

Üç adım vardır, üçü de birbirinden bağımsız tekrar denenebilir:

1. `POST photos/upload-url` → kısa ömürlü imzalı adres (10 dk)
2. `PUT <imzalı adres>` → dosyanın kendisi
3. `POST photos` → kaydın kesinleştirilmesi

İkinci adımı **Laravel'in kendi storage rotası** karşılar; uygulama kodu
dosyaya dokunmaz. Yerel disk sürücüsü `temporaryUploadUrl()` desteklediği
için MinIO/S3 kurmadan çalışır (`local` diskinde `'serve' => true`).
Üretimde `FILESYSTEM_DISK=s3` yeterlidir; istemci akışı değişmez.

> Yükleme rotası **yalnızca imzayı** doğrular — içerik türüne ve boyuta
> bakmaz, gövdede ne gelirse o anahtara yazar. Bu yüzden gerçek denetim
> 3. adımdadır: dosyanın diskteki asıl boyutuna ve JPEG olup olmadığına
> (magic byte) orada bakılır. `upload-url` aşamasındaki `content_type` ve
> `byte_size` doğrulaması erken uyarıdır, güvenlik sınırı değildir.

`upload_url` adresinin host'u **isteğin host'undan** üretilir, `APP_URL`'den
değil. Telefon `192.168.1.x:8000` üzerinden istediğinde adres de o host ile
döner, yani doğrudan erişilebilir.

`storage_key` istemcinin ürettiği id'den türetilir
(`photos/{institution_id}/{photo_id}.jpg`), bu yüzden adres kaç kez
istenirse istensin aynıdır. Kesinleştirmede gövdedeki `storage_key` beklenen
değerle karşılaştırılır; başkasının dosyası sahiplenilemez.

`taken_at` fotoğrafın **çekildiği** andır, sunucunun aldığı an değil —
`records.recorded_at` ile aynı mantık.

Etiketlenen çocukların hepsi o sınıfa ait olmalı ve `photo_consent` değeri
`granted` olmalıdır. Değilse 422 döner ve sorunlu id'ler yanıtta listelenir:

```json
{
  "message": "Fotoğraf izni olmayan çocuk etiketlenemez.",
  "errors": {"child_ids": ["Fotoğraf izni olmayan çocuk etiketlenemez."]},
  "blocked_child_ids": ["...", "..."]
}
```

`child_ids` boş dizi olabilir (etiketsiz sınıf fotoğrafı), ama alanın kendisi
gövdede bulunmalıdır.

### Sahipsiz dosyalar

Yükleme ile kesinleştirme ayrı adımlar olduğu için dosya diske yazılıp
`POST photos` hiç gelmeyebilir. Bu dosyaların satırı yoktur ve kendiliğinden
gitmezler:

```
php artisan photos:prune --hours=48        # varsayılan 48 saat
php artisan photos:prune --dry-run         # yalnızca ne silineceğini yaz
```

Kesinleştirilmiş dosyalar ve eşikten yeni dosyalar korunur — kuyruk saatlerce
çevrimdışı bekleyebildiği için eşik geniş tutulmuştur.

## Günü gönderme

`POST classrooms/{classroom}/day-send` sınıfın bir gününü gönderilmiş olarak
işaretler ve o andaki özeti kaydeder. **Veliye gerçek bildirim göndermez** —
veli tarafı ayrı bir adımdır.

İki farklı tekrar durumu vardır ve **ikisi de 200 döner**:

- aynı `id` → kuyruk aynı isteği tekrar gönderdi
- aynı gün, farklı `id` → öğretmen ikinci kez bastı

İkincisine **409 dönülmez**: istemci 409'u kalıcı ret sayıp öğretmene
"gönderilemedi" derdi, hâlbuki gün gitmiştir.

### Yeniden gönderme

Gün, gövdede **`resend: true`** varken yeniden gönderilebilir. Bu, yukarıdaki
iki tekrardan ayrılan üçüncü durumdur ve **201** döner: yeni bir `day_sends`
satırı açılır (`attempt` bir artar) ve veliye ikinci bildirim çıkar.

Kural üçe iner:

| gövde | sunucuda | sonuç |
|---|---|---|
| aynı `id` | görülmüş | 200, bildirim **yok** (kuyruk tekrarı) |
| yeni `id`, `resend` yok | gün gönderilmiş | 200, bildirim **yok** (eski davranış) |
| yeni `id`, `resend: true` | gün gönderilmiş | **201**, yeni satır, bildirim **var** |

**Çıkarım yapılmaz.** "Yeni id geldiyse öğretmen tekrar basmıştır" deseydik,
yanıt ağ üzerinde kaybolup kuyruk yeni bir id ile tekrar denediğinde veli
ikinci SMS'i alırdı. Açık bayrak, kuyruğun tekrarı ile öğretmenin ikinci
**niyetini** ayıran tek güvenilir işarettir. Bayrak gelse bile gün hiç
gönderilmemişse bu ilk gönderimdir (`attempt: 1`, `resend: false`).

> **Bedeli:** yeniden gönderim bağlantıları yeniden üretir ve bir kapsam için
> tek canlı bağlantı olduğundan **velinin elindeki eski adres ölür**; onu
> kaydetmiş veli 403 alır. Ham token saklanmadığı için "eskisini tekrar
> gönder" seçeneği yoktur.

Bir sınıfın bir günü artık tek kayıt değildir; tekil kısıt
`unique(classroom_id, day, attempt)`. `attempt`'i **sunucu** hesaplar ve bu
kısıt yarışı önler: aynı anda gelen iki istek aynı sırayı dener, birini
veritabanı reddeder ve kaybeden taraf bildirim üretmeden mevcut kaydı döner.
Satır kilidi yerine tekil kısıt seçildi — SQLite ile PostgreSQL arasında
davranış farkı bırakmıyor.

Satırlar birikir, güncellenmez: her satırın sayıları **o anın** değeridir,
böylece günün gönderim geçmişi okunabilir kalır.

`requested_at` butona basıldığı andır, `sent_at` sunucunun işlediği an —
ikisi ayrı tutulur. `day` öğretmenin **yerel takvim günüdür** (`YYYY-MM-DD`)
ve UTC'ye çevrilmez.

Özeti sunucu hesaplar. `photo_count` istek anında elde ne varsa onu sayar;
fotoğraflar ayrı kuyrukta olduğu için sonradan gelen fotoğraf sayıyı
değiştirebilir. `parent_count` **DISTINCT** veli sayısıdır (kardeşler tek
sayılır); veli tablosu yokken bu alan null dönüyordu.

**Boş gün sunucuda reddedilmez.** Kural yalnızca istemcidedir. Sebep somut:
kayıtlar ve fotoğraflar ayrı kuyruklardadır, `photo_count` gönderim anında
sayılır ve sadece fotoğraf çekilmiş gerçek bir gün, yüklemeler bitmeden
gönderilirse sunucuda 0 kayıt + 0 fotoğraf görünür. 422 istemcide kalıcı ret
olduğu için yanlış pozitifin bedeli telafisi olmayan ölü gün olurdu. Boş gün
fotoğraf izni gibi bir güvenlik sınırı değildir; kötü ihtimalle gereksiz bir
bildirim çıkar.

`GET classrooms` yanıtındaki `day_sent_at`, o sınıfın **bugüne** ait
gönderimi varsa damgasıdır, yoksa `null`. Günün birden fazla gönderimi olduğunda
**en sonuncusunun** damgasıdır: öğretmenin sorduğu şey "veli en son ne zaman
haber aldı". Sınıf kartındaki "✓ Gönderildi" şeridi bunu kullanır.

> `day` bir tarih kolonudur ama sürücüye göre saat bilgisiyle saklanabilir;
> bu yüzden gün karşılaştırmaları `whereDate` ile yapılır. Düz eşitlik
> PostgreSQL'de çalışıp SQLite'ta sessizce boş dönüyordu.

## Veli tarafı

Veli uygulamayı kullanmaz; kendisine gönderilen bağlantıyla açılan tek bir
web sayfası görür.

### Veri

`parents` tablosu velileri, `child_parent` pivotu çocuk-veli bağını tutar.
Bir çocuğun birden fazla velisi, bir velinin birden fazla çocuğu olabilir —
bu yüzden veli sayısı çocuk sayısından türetilmez, **DISTINCT** sayılır
(`GET classrooms` ve `day-send` yanıtlarındaki `parent_count`).

Telefonlar E.164 olarak saklanır (`05525700853` → `+905525700853`).
Çözülemeyen girdi `null` döner, uydurulmaz.

> PHP'de `Parent` ayrılmış bir kelimedir ve sınıf adı olamaz. Model adı bu
> yüzden `Guardian`, tablo adı `parents`.

Geliştirme için tek bir test velisi `.env` içindeki `DEV_PARENT_PHONE`'dan
oluşturulur. Numara koda ve depoya girmez; env boşsa veli hiç oluşturulmaz.

```
php artisan db:seed --class=DevParentSeeder
```

### Sihirli bağlantı

Kapsam **bir veli + bir çocuk + bir gün**. Gün de kapsama dahildir: akşam
gönderilen bir bağlantı ertesi sabah boş sayfa göstermesin, gönderildiği
günü göstersin diye.

Token 32 rastgele bayttır (base64url, 43 karakter) ve veritabanında **yalnızca
sha256 özeti** saklanır. Veritabanı sızarsa eldeki özetlerden çalışan bir
bağlantı üretilemez. Süre 7 gün.

Rota `GET /v/{token}` — public, oturumsuz, `api/v1` altında değil. Geçersiz
ve süresi dolmuş token **aynı** sayfayı görür (404), böylece token'ın var
olup olmadığı dışarıdan anlaşılmaz.

Bir kapsam için **aynı anda tek canlı bağlantı** bulunur: yeni bağlantı
üretildiğinde eskisi `revoked_at` ile iptal edilir (satır silinmez, iz
kalır). Aksi halde `parent:links` her çalıştığında bir anahtar daha eklenir,
hiçbiri geri alınamaz ve log'a ya da iletilen bir mesaja düşen her adres
süresi bitene kadar canlı kalırdı.

> Bunun bedeli: yeni bağlantı üretmek daha önce paylaşılan adresi geçersiz
> kılar. "Eskisini yeniden göster" seçeneği yok, çünkü ham token saklanmıyor —
> saklasaydık veritabanı sızıntısına karşı korumayı kaybederdik.

### Veli sayfası

Tek çocuğun tek günü: yoklama, kahvaltı/öğle, uyku, tuvalet/bez ve o çocuğun
etiketli olduğu fotoğraflar. Sınıfın diğer çocuklarına ait hiçbir veri —
isim, sayı, fotoğraf — bu sayfaya girmez.

- Aynı türde birden fazla kayıt varsa **sonuncusu** geçerlidir; öğretmen
  "geldi"yi "gelmedi"ye düzeltmişse veli son hâlini görür.
- Fotoğraflar kısa ömürlü imzalı adreslerle verilir. Yol adresin içinde geçer
  ama imzasız istek **403** alır ve imza kısa sürede geçersizleşir.
- `photo_consent` sayfa çizilirken **yeniden** kontrol edilir; izin sonradan
  geri alınmışsa eski fotoğraf da görünmez.
- Kayıt yoksa "henüz kayıt girilmemiş" denir, boş alanlar uydurulmaz.
- Saatler `kres.display_timezone` (varsayılan `Europe/Istanbul`) ile gösterilir.
  Damgalar UTC saklandığı için çevrilmezse veli 23:47'deki kaydı 20:47 görür.

### Kuru çalışma (SMS yok)

Gönderim `ParentLinkChannel` arayüzünün arkasındadır. Şu anki tek uygulama
`LogChannel`: **SMS göndermez**, bağlantıyı log'a yazar (telefon maskeli).
Sağlayıcı geldiğinde arayüzü uygulayan yeni bir sınıf yazıp
`config/kres.php` içindeki `parent_link_channel` değerini değiştirmek yeterli;
`day-send` koduna dokunulmaz.

Bağlantılar **API yanıtında dönmez** — öğretmenin telefonunda veli linki
tutmanın faydası yok, sızma yüzeyi artar. Kuru çalışmada bağlantıya ulaşmanın
yolu şudur:

```
php artisan parent:links --classroom=Papatyalar --day=2026-08-23
php artisan parent:links --classroom=Papatyalar --day=2026-08-23 --url=http://192.168.1.2:8000
```

Komut **varsayılan olarak bağlantı üretmez**. Canlı bir bağlantı varsa
yalnızca varlığını ve üretim saatini bildirir; adresi gösteremez, çünkü ham
token saklanmıyor. Üretim mevcut bağlantıyı iptal ettiği için, hata ayıklamak
üzere çalıştırılan bir komut velinin elindeki adresi sessizce öldürürdü.

Yeni bağlantı açıkça istenir ve komut ne olacağını önden söyler:

```
php artisan parent:links --classroom=Papatyalar --day=2026-08-23 --rotate
```

> CLI'da istek host'u yoktur ve adres `APP_URL`'e düşer — o da genellikle
> `localhost`'tur. Telefonda `localhost` telefonun kendisi demektir; bu yüzden
> `--url` vardır ve komut adres localhost çıktığında kendiliğinden uyarır.

Bağlantılar yalnızca **yeni** gönderimde üretilir; tekrar gönderimde (200
dönen yollar) veliye ikinci kez bildirim gitmez.

## Kurum izolasyonu

`App\Models\Concerns\BelongsToInstitution` trait'i `Classroom` ve `Child`
modellerine global scope ekler. Kurum kimliği **hiçbir zaman** URL'den veya
request gövdesinden okunmaz, yalnızca `auth()->user()->institution_id`
üzerinden gelir.

Scope, kimlik doğrulanmamış bağlamda (seeder, tinker, konsol) devre dışı
kalır. Tüm uç noktalar Sanctum ile korunduğu için bu durum API'ye açılmaz.
