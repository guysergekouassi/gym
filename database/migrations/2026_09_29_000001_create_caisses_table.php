<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisses', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->string('emplacement', 150)->nullable(); // ex. Accueil, Bar, Espace femmes
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('caisse_id')->nullable()->after('role')->constrained('caisses')->nullOnDelete();
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->foreignId('caisse_id')->nullable()->after('user_id')->constrained('caisses')->nullOnDelete();
            $table->index(['caisse_id', 'created_at']);
        });

        // Installation existante : une caisse principale reprend les caissières et l'historique
        $aReprendre = DB::table('users')->where('role', 'caissier')->exists() || DB::table('paiements')->exists();

        if ($aReprendre) {
            $caisseId = DB::table('caisses')->insertGetId([
                'nom' => 'Caisse principale',
                'emplacement' => 'Accueil',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('role', 'caissier')->whereNull('caisse_id')->update(['caisse_id' => $caisseId]);
            DB::table('paiements')->whereNull('caisse_id')->update(['caisse_id' => $caisseId]);
        }
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex(['caisse_id', 'created_at']);
            $table->dropConstrainedForeignId('caisse_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caisse_id');
        });

        Schema::dropIfExists('caisses');
    }
};
