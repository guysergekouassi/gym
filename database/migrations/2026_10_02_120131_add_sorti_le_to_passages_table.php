<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            // Heure de sortie : renseignée au 2e scan de l'abonné (après min. 5 min)
            $table->dateTime('sorti_le')->nullable()->after('passe_le');
        });
    }

    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            $table->dropColumn('sorti_le');
        });
    }
};
