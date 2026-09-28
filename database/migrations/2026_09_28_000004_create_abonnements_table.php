<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formule_id')->constrained()->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('montant');
            $table->string('statut', 20)->default('actif'); // actif | annule
            $table->boolean('est_renouvellement')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'date_fin']);
            $table->index(['statut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};
