<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // Yumusak silme. Sert silme secseydik, agda gecikmis bir POST
            // silinen kaydi yeniden olusturabilirdi; burada deleted_at dolu
            // oldugu icin POST kaydi diriltmeden basarili sayilabiliyor.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
