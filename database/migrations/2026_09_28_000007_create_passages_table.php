<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lecteur_id')->nullable()->constrained('lecteurs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paiement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('methode', 20); // empreinte | caisse
            $table->string('statut', 20); // autorise | refuse
            $table->string('motif', 50)->nullable();
            $table->string('empreinte_id', 64)->nullable(); // ID scanné, utile si empreinte inconnue
            $table->dateTime('passe_le')->index();
            $table->timestamps();

            $table->index(['client_id', 'statut', 'passe_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};
