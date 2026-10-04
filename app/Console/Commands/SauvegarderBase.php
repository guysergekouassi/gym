<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Sauvegarde quotidienne de la base SQLite (lancée par demarrer.bat).
 * Copie cohérente même pendant l'utilisation (VACUUM INTO), 30 dernières gardées.
 */
class SauvegarderBase extends Command
{
    protected $signature = 'salle:sauvegarder {--garder=30 : Nombre de sauvegardes conservées}';

    protected $description = 'Sauvegarde la base de données dans storage/app/sauvegardes';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->warn('Base MySQL : utilisez la sauvegarde de Laragon / mysqldump.');

            return self::SUCCESS;
        }

        if (config('database.connections.sqlite.database') === ':memory:') {
            $this->line('Base en mémoire (tests) : rien à sauvegarder.');

            return self::SUCCESS;
        }

        $dossier = storage_path('app/sauvegardes');
        File::ensureDirectoryExists($dossier);
        $fichier = $dossier.DIRECTORY_SEPARATOR.'sauvegarde-'.now()->format('Y-m-d').'.sqlite';

        if (File::exists($fichier)) {
            $this->line('Sauvegarde du jour déjà faite.');

            return self::SUCCESS;
        }

        DB::statement('VACUUM INTO ?', [$fichier]);
        $this->info('Sauvegarde : '.$fichier);

        // Les plus anciennes au-delà de N sont supprimées
        collect(File::glob($dossier.DIRECTORY_SEPARATOR.'sauvegarde-*.sqlite'))
            ->sort()->reverse()->slice(max(1, (int) $this->option('garder')))
            ->each(fn ($ancien) => File::delete($ancien));

        return self::SUCCESS;
    }
}
