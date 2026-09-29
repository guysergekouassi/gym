<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Étape 1 : annulations, clôtures, journal, rappels, gels, cartes d'accès. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->timestamp('annule_le')->nullable()->after('reference');
            $table->foreignId('annule_par')->nullable()->after('annule_le')->constrained('users')->nullOnDelete();
            $table->string('motif_annulation', 255)->nullable()->after('annule_par');
        });

        Schema::create('clotures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('jour');
            $table->unsignedInteger('fond_caisse')->default(0);
            $table->unsignedInteger('especes_encaissees');
            $table->unsignedInteger('especes_comptees');
            $table->integer('ecart');
            $table->unsignedInteger('electronique');
            $table->json('coupures')->nullable();
            $table->string('motif_ecart', 255)->nullable();
            $table->timestamps();

            $table->unique(['caisse_id', 'jour']);
        });

        Schema::create('journal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->nullableMorphs('sujet');
            $table->string('resume', 255);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_id')->nullable();
            $table->string('type', 30); // expiration, inactif, anniversaire, essai, recu, campagne, lien_membre
            $table->string('canal', 20)->default('whatsapp');
            $table->string('telephone', 20)->nullable();
            $table->text('contenu');
            $table->string('statut', 20)->default('a_envoyer'); // a_envoyer, envoye, ignore, echec
            $table->timestamp('envoye_le')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('erreur', 255)->nullable();
            $table->foreignId('campagne_id')->nullable();
            $table->date('pour_le')->index();
            $table->timestamps();

            $table->index(['client_id', 'type', 'pour_le']);
            $table->index(['statut', 'pour_le']);
        });

        Schema::create('gels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('du');
            $table->date('au');
            $table->unsignedInteger('jours');
            $table->string('motif', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'du', 'au']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('carte_id', 64)->nullable()->unique()->after('empreinte_id');
            $table->string('code_acces', 16)->nullable()->unique()->after('carte_id');
            $table->string('jeton_membre', 64)->nullable()->unique()->after('code_acces');
        });

        // Chaque client existant reçoit son code personnel (QR code d'accès)
        DB::table('clients')->whereNull('code_acces')->orderBy('id')->each(function ($client) {
            DB::table('clients')->where('id', $client->id)->update(['code_acces' => 'GF'.strtoupper(Str::random(10))]);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['carte_id']);
            $table->dropUnique(['code_acces']);
            $table->dropUnique(['jeton_membre']);
            $table->dropColumn(['carte_id', 'code_acces', 'jeton_membre']);
        });
        Schema::dropIfExists('gels');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('journal');
        Schema::dropIfExists('clotures');
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['annule_le', 'motif_annulation']);
        });
    }
};
