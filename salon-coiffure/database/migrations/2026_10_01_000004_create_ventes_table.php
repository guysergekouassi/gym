<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // T-20261001-0001
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total');
            $table->string('mode_paiement', 20);
            $table->unsignedInteger('montant_recu')->nullable();
            $table->unsignedInteger('monnaie_rendue')->default(0);
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_annulation')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'annulee_at']);
        });

        Schema::create('vente_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            // Libellé et prix figés au moment de la vente : modifier un prix ne change pas l'historique
            $table->string('libelle', 100);
            $table->unsignedInteger('prix_unitaire');
            $table->unsignedSmallInteger('quantite');
            $table->unsignedInteger('total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vente_lignes');
        Schema::dropIfExists('ventes');
    }
};
