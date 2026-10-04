<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sens du passage : arrivée, départ, ou badge en trop (séance du jour déjà enregistrée). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->string('sens', 10)->nullable()->after('statut'); // entree | depart | deja
            $table->index(['sens', 'passe_le']);
        });
    }

    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->dropIndex(['sens', 'passe_le']);
            $table->dropColumn('sens');
        });
    }
};
