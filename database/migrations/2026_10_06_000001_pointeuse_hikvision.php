<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pointeuse Hikvision (ISAPI) : c'est l'application qui interroge la pointeuse
     * (adresse IP + compte admin de la pointeuse), et non l'inverse.
     */
    public function up(): void
    {
        Schema::table('lecteurs', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->default(80)->after('adresse_ip');
            $table->string('identifiant', 32)->default('admin')->after('port');
            $table->text('mot_de_passe')->nullable()->after('identifiant'); // chiffré (APP_KEY)
            $table->string('modele', 60)->nullable()->after('mot_de_passe');
            $table->timestamp('dernier_evenement_le')->nullable()->after('derniere_activite_at');
            $table->string('derniere_erreur', 255)->nullable()->after('dernier_evenement_le');
            $table->dropColumn('stamp_pointages');
        });

        // Les commandes ZKTeco éventuellement en file n'ont plus de sens
        \Illuminate\Support\Facades\DB::table('commandes_pointeuse')->delete();
    }

    public function down(): void
    {
        Schema::table('lecteurs', function (Blueprint $table) {
            $table->dropColumn(['port', 'identifiant', 'mot_de_passe', 'modele', 'dernier_evenement_le', 'derniere_erreur']);
            $table->string('stamp_pointages', 30)->nullable();
        });
    }
};
