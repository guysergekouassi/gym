<?php

namespace App\Console\Commands;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\CommandePointeuse;
use App\Models\Paiement;
use App\Models\Passage;
use App\Services\SynchroPointeuseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Remise à zéro avant la mise en service : efface les données de test
 * (clients, abonnements, encaissements, passages) et retire ces membres de la pointeuse.
 * Conserve les comptes du personnel, les formules et tarifs, les paramètres et les pointeuses.
 * Une copie de la base est faite juste avant, au cas où.
 */
class EffacerDonnees extends Command
{
    protected $signature = 'salle:effacer-donnees {--force : Ne pas demander de confirmation}';

    protected $description = 'Efface les données de test (clients, abonnements, encaissements, passages)';

    public function handle(SynchroPointeuseService $pointeuse): int
    {
        $this->table(['À effacer', 'Nombre'], [
            ['Clients (y compris masqués)', Client::withTrashed()->count()],
            ['Abonnements', Abonnement::count()],
            ['Encaissements / tickets', Paiement::count()],
            ['Passages (entrées, départs, refus)', Passage::count()],
        ]);
        $this->line('Conservés : comptes du personnel, formules et tarifs, paramètres, pointeuses.');

        if (! $this->option('force') && $this->ask('Tapez EFFACER pour confirmer') !== 'EFFACER') {
            $this->warn('Annulé : rien n\'a été effacé.');

            return self::FAILURE;
        }

        $copie = $this->copieDeSecurite();

        $numeros = Client::withTrashed()->whereNotNull('empreinte_id')->pluck('empreinte_id');
        $photos = Client::withTrashed()->whereNotNull('photo_path')->pluck('photo_path');

        DB::transaction(function () use ($numeros, $pointeuse) {
            Passage::query()->delete();
            Paiement::query()->delete();
            Abonnement::query()->delete();
            Client::withTrashed()->forceDelete();

            // Les membres de test sont aussi retirés de la pointeuse (envoyé par la fenêtre « Pointeuse »)
            CommandePointeuse::query()->delete();
            $numeros->each(fn ($numero) => $pointeuse->retirer((string) $numero));
        });

        Storage::disk('public')->delete($photos->all());

        $this->info('Données de test effacées.'.($copie ? " Copie de sécurité : {$copie}" : ''));
        if ($numeros->isNotEmpty()) {
            $this->line("{$numeros->count()} membre(s) seront retirés de la pointeuse dans quelques secondes (fenêtre « Pointeuse » ouverte).");
        }

        return self::SUCCESS;
    }

    private function copieDeSecurite(): ?string
    {
        $base = config('database.connections.sqlite.database');
        if (config('database.default') !== 'sqlite' || $base === ':memory:') {
            return null;
        }

        $dossier = storage_path('app/sauvegardes');
        File::ensureDirectoryExists($dossier);
        $fichier = $dossier.DIRECTORY_SEPARATOR.'avant-effacement-'.now()->format('Y-m-d-His').'.sqlite';
        DB::statement('VACUUM INTO ?', [$fichier]);

        return $fichier;
    }
}
