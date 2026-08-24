<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gunun VELIYE YENIDEN gonderilebilmesi.
 *
 * Once bir sinifin bir gunu yalnizca BIR kez gonderilebiliyordu:
 * `(classroom_id, day)` tekildi ve her tekrar mevcut kaydi 200 ile donuyordu.
 * Bunun sebebi cevrimdisi kuyrugun ayni istegi tekrarlayabilmesiydi; tekrar
 * bildirim veliye ikinci SMS demekti.
 *
 * Ama bu kural iki AYRI seyi ayni sepete koyuyordu: kuyrugun tekrari ile
 * ogretmenin ikinci kez basmasi. Sabah erken gonderilen gunun ogleden sonraki
 * kayitlari veliye bir daha BILDIRILEMIYORDU (veri gorunuyordu -- baglanti
 * 7 gun canli ve sayfa kayitlari anlik okuyor -- cikmayan sey ikinci haberdi).
 *
 * Artik her KABUL EDILEN gonderim kendi satiri: gun bir kez gonderilir, ama
 * acikca `resend` istenirse yeni bir satir daha acilir ve bildirim yeniden
 * cikar. Satirlar birikince gunun gecmisi de okunabilir olur (kim, ne zaman,
 * o anda kac fotograf vardi).
 *
 * `attempt` yalnizca YARISI onlemek icin var. Tekil kisit kaldirilinca es
 * zamanli iki istek iki satir acip veliye iki SMS gonderebilirdi. Sunucu
 * sirayi kendi hesapladigi icin ayni anda gelen iki istek ayni `attempt`
 * degerini dener, birini veritabani reddeder ve kaybeden taraf bildirim
 * uretmeden mevcut kaydi doner. Satir kilidi yerine tekil kisit secildi:
 * SQLite ile Postgres arasinda davranis farki birakmiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_sends', function (Blueprint $table) {
            // Gunun kacinci gonderimi. Mevcut satirlar tek gonderimdi.
            $table->unsignedInteger('attempt')->default(1)->after('day');

            // Bu satir acik bir YENIDEN gonderim mi? Ilk gonderim false.
            $table->boolean('resend')->default(false)->after('attempt');
        });

        Schema::table('day_sends', function (Blueprint $table) {
            $table->dropUnique(['classroom_id', 'day']);
        });

        Schema::table('day_sends', function (Blueprint $table) {
            $table->unique(['classroom_id', 'day', 'attempt']);
            // "Bu gun gonderildi mi" ve "en son ne zaman" sorgulari icin.
            $table->index(['classroom_id', 'day']);
        });
    }

    /**
     * Geri alma, bir gunde birden fazla gonderim varsa tekil kisiti geri
     * koyamaz ve hata verir. Bilerek: fazla satirlari sessizce silmek gercek
     * bir gonderim kaydini yok etmek olurdu.
     */
    public function down(): void
    {
        Schema::table('day_sends', function (Blueprint $table) {
            $table->dropUnique(['classroom_id', 'day', 'attempt']);
            $table->dropIndex(['classroom_id', 'day']);
        });

        Schema::table('day_sends', function (Blueprint $table) {
            $table->dropColumn(['attempt', 'resend']);
        });

        Schema::table('day_sends', function (Blueprint $table) {
            $table->unique(['classroom_id', 'day']);
        });
    }
};
