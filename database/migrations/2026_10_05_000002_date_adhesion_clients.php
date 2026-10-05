<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Date à laquelle le membre a rejoint la salle (remplace la date de naissance dans le formulaire)
        Schema::table('clients', function (Blueprint $table) {
            $table->date('date_adhesion')->nullable()->after('email');
        });

        // Clients existants : date de création de la fiche
        DB::table('clients')->whereNull('date_adhesion')->update(['date_adhesion' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('date_adhesion');
        });
    }
};
