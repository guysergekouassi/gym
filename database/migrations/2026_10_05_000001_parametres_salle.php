<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Nom affiché par défaut : « Gym » (modifiable dans Administration → Paramètres)
    public function up(): void
    {
        if (! DB::table('parametres')->where('cle', 'salle_nom')->exists()) {
            DB::table('parametres')->insert(['cle' => 'salle_nom', 'valeur' => 'Gym', 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::forget('parametres.tous');
    }

    public function down(): void
    {
        DB::table('parametres')->where('cle', 'salle_nom')->where('valeur', 'Gym')->delete();
        Cache::forget('parametres.tous');
    }
};
