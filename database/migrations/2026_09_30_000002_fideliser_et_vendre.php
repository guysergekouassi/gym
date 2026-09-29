<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Étape 2 : formules avancées, promos, prospects, cours, coachs, boutique, paiement en ligne. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formules', function (Blueprint $table) {
            $table->string('type', 20)->default('abonnement')->after('nom'); // abonnement, carnet, coaching
            $table->string('categorie', 50)->nullable()->after('type'); // Standard, Étudiant, Couple, Entreprise
            $table->unsignedInteger('nb_entrees')->nullable()->after('duree_jours');
            $table->unsignedInteger('nb_seances')->nullable()->after('nb_entrees');
            $table->string('description', 255)->nullable()->after('prix');
        });

        Schema::create('codes_promo', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 20); // pourcentage, montant
            $table->unsignedInteger('valeur');
            $table->date('expire_le')->nullable();
            $table->unsignedInteger('utilisations_max')->nullable();
            $table->unsignedInteger('utilisations')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->unsignedInteger('entrees_restantes')->nullable()->after('montant'); // carnets
            $table->unsignedInteger('remise')->default(0)->after('entrees_restantes');
            $table->unsignedInteger('frais_inscription')->default(0)->after('remise');
            $table->foreignId('code_promo_id')->nullable()->after('frais_inscription')->constrained('codes_promo')->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('parrain_id')->nullable()->after('jeton_membre')->constrained('clients')->nullOnDelete();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->string('telephone', 20)->nullable();
            $table->string('source', 50)->nullable(); // bouche-à-oreille, Facebook, passage, parrainage…
            $table->string('statut', 20)->default('nouveau'); // nouveau, essai_prevu, essai_fait, inscrit, perdu
            $table->dateTime('essai_le')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coachs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('telephone', 20)->nullable();
            $table->string('specialite', 100)->nullable();
            $table->unsignedTinyInteger('commission_pct')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->foreignId('coach_id')->nullable()->constrained('coachs')->nullOnDelete();
            $table->unsignedTinyInteger('jour_semaine'); // 1 = lundi … 7 = dimanche
            $table->time('heure');
            $table->unsignedSmallInteger('duree_minutes')->default(60);
            $table->unsignedSmallInteger('capacite')->default(20);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('statut', 20)->default('reservee'); // reservee, attente, presente, absente, annulee
            $table->timestamps();

            $table->unique(['cours_id', 'client_id', 'date']);
            $table->index(['cours_id', 'date', 'statut']);
        });

        Schema::create('packs_coaching', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('coachs')->nullOnDelete();
            $table->foreignId('formule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paiement_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('seances_total');
            $table->unsignedInteger('seances_restantes');
            $table->unsignedInteger('montant');
            $table->date('expire_le')->nullable();
            $table->string('statut', 20)->default('actif'); // actif, termine, annule
            $table->timestamps();
        });

        Schema::create('seances_coaching', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs_coaching')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('coachs')->nullOnDelete();
            $table->dateTime('faite_le');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->unsignedInteger('prix');
            $table->integer('stock')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(5);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('paiement_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('libelle', 150);
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('prix_unitaire');
            $table->timestamps();
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained()->cascadeOnDelete();
            $table->integer('quantite'); // + entrée, − sortie
            $table->string('motif', 100); // approvisionnement, vente, annulation, correction
            $table->foreignId('paiement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('paiements_en_ligne', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 60)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formule_id')->constrained();
            $table->unsignedInteger('montant');
            $table->string('statut', 20)->default('en_attente'); // en_attente, accepte, refuse
            $table->foreignId('paiement_id')->nullable()->constrained()->nullOnDelete();
            $table->json('reponse')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_en_ligne');
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('paiement_lignes');
        Schema::dropIfExists('produits');
        Schema::dropIfExists('seances_coaching');
        Schema::dropIfExists('packs_coaching');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('cours');
        Schema::dropIfExists('coachs');
        Schema::dropIfExists('prospects');
        Schema::table('clients', fn (Blueprint $t) => $t->dropConstrainedForeignId('parrain_id'));
        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('code_promo_id');
            $table->dropColumn(['entrees_restantes', 'remise', 'frais_inscription']);
        });
        Schema::dropIfExists('codes_promo');
        Schema::table('formules', fn (Blueprint $t) => $t->dropColumn(['type', 'categorie', 'nb_entrees', 'nb_seances', 'description']));
    }
};
