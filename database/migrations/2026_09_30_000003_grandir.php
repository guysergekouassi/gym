<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Étape 3 : plusieurs salles, suivi physique, campagnes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->string('adresse', 200)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        foreach (['caisses', 'lecteurs', 'cours', 'passages'] as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            });
        }

        // Installation existante : tout est rattaché à une première salle
        $existe = DB::table('caisses')->exists() || DB::table('lecteurs')->exists() || DB::table('passages')->exists();
        if ($existe) {
            $salleId = DB::table('salles')->insertGetId([
                'nom' => config('salle.nom', 'Salle principale'),
                'adresse' => config('salle.adresse'),
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (['caisses', 'lecteurs', 'cours', 'passages'] as $nomTable) {
                DB::table($nomTable)->whereNull('salle_id')->update(['salle_id' => $salleId]);
            }
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->string('objectif', 255)->nullable()->after('notes');
            $table->text('programme')->nullable()->after('objectif');
        });

        Schema::create('mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('poids', 5, 1)->nullable();
            $table->unsignedSmallInteger('taille')->nullable(); // cm
            $table->decimal('tour_taille', 5, 1)->nullable();
            $table->decimal('masse_grasse', 4, 1)->nullable(); // %
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campagnes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('segment', 40);
            $table->text('contenu');
            $table->unsignedInteger('destinataires')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagnes');
        Schema::dropIfExists('mesures');
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['objectif', 'programme']));
        foreach (['caisses', 'lecteurs', 'cours', 'passages'] as $nomTable) {
            Schema::table($nomTable, fn (Blueprint $t) => $t->dropConstrainedForeignId('salle_id'));
        }
        Schema::dropIfExists('salles');
    }
};
