<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');

            // E.164 normalize edilmis hali: +905525700853
            $table->string('phone');
            // Girildigi haliyle; normalize kurali degisirse kaynagi kaybetmeyelim.
            $table->string('phone_raw')->nullable();

            $table->timestamps();

            // Ayni kurumda ayni numara tek veli demektir; iki cocugun ayni
            // velisi varsa tek satir olusur ve pivot'tan iki cocuga baglanir.
            $table->unique(['institution_id', 'phone']);
        });

        Schema::create('child_parent', function (Blueprint $table) {
            $table->foreignUuid('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->constrained('parents')->cascadeOnDelete();

            // anne | baba | veli. Veli sayfasinda hitap icin.
            $table->string('relation')->nullable();

            $table->primary(['child_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_parent');
        Schema::dropIfExists('parents');
    }
};
