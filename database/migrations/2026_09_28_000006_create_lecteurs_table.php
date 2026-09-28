<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('token_hash', 64)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamp('derniere_activite_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecteurs');
    }
};
