<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            // Iptal edilmis baglanti. Satir silinmez ki "kime ne zaman
            // baglanti uretildi" izi kalsin, ama artik acilmaz.
            $table->timestampTz('revoked_at')->nullable();
        });

        // Mevcut cop: her (veli, cocuk, gun) icin EN YENISI disindaki tum
        // baglantilar iptal edilir. Once her calistirmada yeni token
        // uretiliyor ve eskiler canli kaliyordu; ayni gun icin bes ayri
        // anahtar demek, iptal diye bir sey olmamasi demekti.
        $sonlar = DB::table('magic_links')
            ->selectRaw('parent_id, child_id, day, max(created_at) as son')
            ->groupBy('parent_id', 'child_id', 'day')
            ->get();

        foreach ($sonlar as $son) {
            DB::table('magic_links')
                ->where('parent_id', $son->parent_id)
                ->where('child_id', $son->child_id)
                ->where('day', $son->day)
                ->where('created_at', '<', $son->son)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
