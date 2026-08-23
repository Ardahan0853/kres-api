<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_sends', function (Blueprint $table) {
            // id ISTEMCI uretir; gonderim kuyrugu ayni istegi tekrarlayabilir.
            $table->uuid('id')->primary();

            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            // Ogretmenin YEREL takvim gunu. UTC'ye cevrilmez; gece yarisina
            // yakin saatlerde gun kayar.
            $table->date('day');

            // Butona basildigi an (cihaz damgasi) ile sunucunun kaydi aldigi an
            // ayri tutulur: ogretmen 17:12'de bahcede basip 19:00'da aga
            // kavusabilir, veliye 17:12 gorunmelidir.
            $table->timestampTz('requested_at');
            $table->timestampTz('sent_at');

            $table->unsignedInteger('child_count');
            $table->unsignedInteger('photo_count');

            $table->timestamps();

            // Bir sinifin bir gunu yalnizca BIR kez gonderilir. Ayni gun farkli
            // bir id ile tekrar gelirse yeni bildirim uretilmemeli.
            $table->unique(['classroom_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_sends');
    }
};
