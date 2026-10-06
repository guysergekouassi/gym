<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formules', function (Blueprint $table) {
            // null = accès illimité ; 1 = une séance par jour (la pointeuse refuse une 2e venue le même jour)
            if (! Schema::hasColumn('formules', 'seances_par_jour')) {
                $table->unsignedTinyInteger('seances_par_jour')->nullable()->after('prix');
            }
            // Avantages affichés à la caisse et sur le ticket, un par ligne
            if (! Schema::hasColumn('formules', 'description')) {
                $table->text('description')->nullable()->after('seances_par_jour');
            }
        });
    }

    public function down(): void
    {
        Schema::table('formules', function (Blueprint $table) {
            $table->dropColumn(['seances_par_jour', 'description']);
        });
    }
};
