<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'appareil est une pointeuse à empreinte (ZKTeco) : l'identifiant est le n° d'utilisateur de la pointeuse
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('badge_id', 'empreinte_id');
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->renameColumn('badge_id', 'empreinte_id');
        });

        DB::table('passages')->where('methode', 'badge')->update(['methode' => 'empreinte']);
        DB::table('passages')->where('motif', 'badge_inconnu')->update(['motif' => 'empreinte_inconnue']);

        // Pointeuse en réseau (protocole « Cloud / ADMS ») : reconnue par son n° de série + son adresse IP
        Schema::table('lecteurs', function (Blueprint $table) {
            $table->string('numero_serie', 50)->nullable()->unique()->after('nom');
            $table->string('adresse_ip', 45)->nullable()->after('numero_serie');
            $table->string('stamp_pointages', 30)->nullable()->after('adresse_ip');
        });

        // Commandes envoyées à la pointeuse (ajout / suppression d'un membre…)
        Schema::create('commandes_pointeuse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecteur_id')->constrained('lecteurs')->cascadeOnDelete();
            $table->string('commande', 500);
            $table->string('statut', 20)->default('en_attente'); // en_attente | envoyee | ok | erreur
            $table->string('retour', 100)->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamp('terminee_le')->nullable();
            $table->timestamps();

            $table->index(['lecteur_id', 'statut']);
        });

        Schema::table('passages', function (Blueprint $table) {
            $table->index(['lecteur_id', 'empreinte_id', 'passe_le']);
        });
    }

    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->dropIndex(['lecteur_id', 'empreinte_id', 'passe_le']);
        });

        Schema::dropIfExists('commandes_pointeuse');

        Schema::table('lecteurs', function (Blueprint $table) {
            $table->dropUnique(['numero_serie']);
            $table->dropColumn(['numero_serie', 'adresse_ip', 'stamp_pointages']);
        });

        DB::table('passages')->where('methode', 'empreinte')->update(['methode' => 'badge']);
        DB::table('passages')->where('motif', 'empreinte_inconnue')->update(['motif' => 'badge_inconnu']);

        Schema::table('passages', function (Blueprint $table) {
            $table->renameColumn('empreinte_id', 'badge_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('empreinte_id', 'badge_id');
        });
    }
};
