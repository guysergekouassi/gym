<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // abonne | journalier
            $table->string('nom', 100);
            $table->string('prenoms', 150)->nullable();
            $table->string('telephone', 20)->nullable()->unique();
            $table->string('email', 150)->nullable();
            $table->date('date_naissance')->nullable();
            $table->char('sexe', 1)->nullable();
            $table->string('photo_path')->nullable();
            // Identifiant de l'utilisateur enrôlé sur le lecteur d'empreinte (jamais l'image du doigt)
            $table->string('empreinte_id', 64)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
