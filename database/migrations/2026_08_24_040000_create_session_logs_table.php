<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_logs', function (Blueprint $table) {
            // id ISTEMCI uretir; kuyruk ayni girisi tekrar gonderebilir.
            $table->uuid('id')->primary();

            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Girisin YAPILDIGI an (cihaz damgasi). 09:00'da cevrimdisi giren
            // ogretmen 11:00'da baglandiginda 09:00 gorunmelidir.
            $table->timestampTz('started_at');

            // Giris cihazda mi dogrulandi. true ise sunucu bu girisi
            // dogrulamamistir; denetimde bu ayrim onemli.
            $table->boolean('offline')->default(false);

            // created_at sunucunun kaydi teslim aldigi andir.
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
