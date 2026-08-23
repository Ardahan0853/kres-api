<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_sends', function (Blueprint $table) {
            // Gonderim anindaki DISTINCT veli sayisi. Veli verisi eklenmeden
            // once gonderilmis kayitlar icin 0 kalir.
            $table->unsignedInteger('parent_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('day_sends', function (Blueprint $table) {
            $table->dropColumn('parent_count');
        });
    }
};
