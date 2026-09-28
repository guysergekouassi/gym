<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 30)->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // caissière
            $table->string('type', 20); // journalier | abonnement
            $table->unsignedInteger('montant');
            $table->string('mode', 20); // especes, orange_money, mtn_momo, moov_money, wave, carte
            $table->string('reference', 100)->nullable(); // référence transaction mobile money
            $table->timestamps();

            $table->index('created_at');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
