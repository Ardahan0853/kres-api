<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_user', function (Blueprint $table) {
            $table->foreignUuid('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['classroom_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_user');
    }
};
