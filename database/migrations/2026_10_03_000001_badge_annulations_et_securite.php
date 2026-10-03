<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le contrôle d'accès se fait par badge (lecteur RFID/carte) : on renomme l'identifiant.
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('empreinte_id', 'badge_id');
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->renameColumn('empreinte_id', 'badge_id');
        });

        DB::table('passages')->where('methode', 'empreinte')->update(['methode' => 'badge']);
        DB::table('passages')->where('motif', 'empreinte_inconnue')->update(['motif' => 'badge_inconnu']);

        // Comptes : changement de mot de passe obligatoire + trace de la dernière connexion
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('doit_changer_mdp')->default(false)->after('actif');
            $table->timestamp('derniere_connexion_at')->nullable()->after('doit_changer_mdp');
        });

        // Annulation d'un encaissement (réservée à l'admin, jamais de suppression)
        Schema::table('paiements', function (Blueprint $table) {
            $table->timestamp('annule_le')->nullable()->after('reference');
            $table->foreignId('annule_par')->nullable()->after('annule_le')->constrained('users')->nullOnDelete();
            $table->string('motif_annulation', 255)->nullable()->after('annule_par');
        });

        // Réglages modifiables par l'admin (tarif du passage journalier, …)
        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle', 50)->primary();
            $table->string('valeur', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annule_par');
            $table->dropColumn(['annule_le', 'motif_annulation']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['doit_changer_mdp', 'derniere_connexion_at']);
        });

        DB::table('passages')->where('methode', 'badge')->update(['methode' => 'empreinte']);
        DB::table('passages')->where('motif', 'badge_inconnu')->update(['motif' => 'empreinte_inconnue']);

        Schema::table('passages', function (Blueprint $table) {
            $table->renameColumn('badge_id', 'empreinte_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('badge_id', 'empreinte_id');
        });
    }
};
