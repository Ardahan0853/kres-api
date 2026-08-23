<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            // Birincil anahtari ISTEMCI uretir (UUIDv7). Cevrimdisi kuyruk ayni
            // kaydi birden fazla kez gonderebildigi icin idempotentlik buna dayanir:
            // ayni id ikinci kez geldiginde yeni satir acilmaz.
            $table->uuid('id')->primary();

            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('child_id')->constrained('children')->cascadeOnDelete();

            // Kaydi giren ogretmen. Ogretmen silinse de kayit durur.
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            // attendance | meal | nap | toilet | note | photo
            $table->string('type');

            // Tip basina degisen serbest JSON. Kayit ekranlari (adim 3) yazilana
            // kadar sekiller kesin degil, bu yuzden burada kati dogrulama yok.
            $table->jsonb('value')->nullable();

            // OLAYIN gercek zamani; istemci damgalar, sunucu asla ezmez.
            // Ogretmen 09:12'de yoklama alip 14:00'te aga kavusabilir.
            $table->timestampTz('recorded_at');

            // created_at sunucunun kaydi teslim aldigi andir, recorded_at ile karistirilmaz.
            $table->timestamps();

            $table->index(['classroom_id', 'recorded_at']);
            $table->index(['child_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
