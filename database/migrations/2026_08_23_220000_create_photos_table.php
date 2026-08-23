<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            // Kayitlarda oldugu gibi id ISTEMCI uretir; yukleme kuyrugu ayni
            // fotografi birden fazla kez kesinlestirmeye calisabilir.
            $table->uuid('id')->primary();

            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            // Diskteki yol. Istemcinin id'sinden turetildigi icin ayni fotograf
            // icin her zaman ayni anahtar olusur.
            $table->string('storage_key')->unique();
            $table->unsignedInteger('byte_size');

            // Fotografin CEKILDIGI an (cihaz damgasi), sunucunun aldigi an degil.
            $table->timestampTz('taken_at');
            $table->timestamps();

            $table->index(['classroom_id', 'taken_at']);
        });

        // Bir fotografta birden fazla cocuk etiketlenebilir. Tablo adi
        // classroom_user ile ayni Laravel kuralini izler (alfabetik).
        Schema::create('child_photo', function (Blueprint $table) {
            $table->foreignUuid('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('child_id')->constrained('children')->cascadeOnDelete();

            $table->primary(['photo_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_photo');
        Schema::dropIfExists('photos');
    }
};
