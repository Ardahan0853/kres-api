<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignUuid('child_id')->constrained('children')->cascadeOnDelete();

            // Link TEK BIR GUNU gosterir. Gun olmasaydi aksam gonderilen link
            // ertesi sabah bos sayfa gosterir ve suresi bitene kadar oyle kalirdi.
            $table->date('day');

            // Ham token ASLA saklanmaz, yalnizca sha256 ozeti. Veritabani
            // sizarsa eldeki ozetlerle link uretilemez.
            $table->string('token_hash', 64)->unique();

            $table->timestampTz('expires_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'child_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');
    }
};
