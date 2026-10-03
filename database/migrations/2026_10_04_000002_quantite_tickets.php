<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plusieurs entrées journalières sur un même ticket (groupe d'amis)
        Schema::table('paiements', function (Blueprint $table) {
            $table->unsignedSmallInteger('quantite')->default(1)->after('montant');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('quantite');
        });
    }
};
