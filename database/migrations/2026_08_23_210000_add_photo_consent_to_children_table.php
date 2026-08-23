<?php

use App\Models\Child;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            // granted | denied | pending. Izin alinmamis kabul etmek guvenli
            // varsayilan oldugu icin yeni satirlar 'pending' baslar.
            $table->string('photo_consent')->default(Child::CONSENT_PENDING);
        });

        // Mevcut satirlarin hepsi varsayilanla 'pending' kalirdi ve izin akisi
        // test edilemezdi. migrate:fresh istemcideki id'leri ve oturumlari
        // dusurdugu icin veriyi bozmadan burada dagitiyoruz; taze kurulumda
        // ayni dagitimi DatabaseSeeder yapar.
        foreach (DB::table('children')->select('classroom_id')->distinct()->pluck('classroom_id') as $classroomId) {
            $ids = DB::table('children')
                ->where('classroom_id', $classroomId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('children')
                    ->where('id', $id)
                    ->update(['photo_consent' => Child::consentForIndex($index)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('photo_consent');
        });
    }
};
