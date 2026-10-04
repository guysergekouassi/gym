<?php

namespace App\Console\Commands;

use App\Models\Lecteur;
use App\Services\PointeuseService;
use Illuminate\Console\Command;

/**
 * Programme d'écoute des pointeuses : à laisser tourner pendant les heures d'ouverture
 * (lancé par demarrer-gymflow.bat). Toutes les 3 secondes, pour chaque pointeuse :
 * envoi des membres modifiés, puis récupération des nouveaux passages.
 */
class EcouterPointeuses extends Command
{
    protected $signature = 'pointeuse:ecouter {--une-fois : Un seul tour puis arrêt (test)} {--intervalle=3 : Secondes entre deux tours}';

    protected $description = 'Relie GymFlow aux pointeuses Hikvision (passages et membres)';

    public function handle(PointeuseService $service): int
    {
        $intervalle = max(1, min(60, (int) $this->option('intervalle')));
        $this->info('Écoute des pointeuses… (Ctrl+C pour arrêter)');

        do {
            $pointeuses = Lecteur::pointeuses()->get();
            if ($pointeuses->isEmpty()) {
                $this->warn('Aucune pointeuse configurée (Administration → Pointeuses).');
            }

            foreach ($pointeuses as $lecteur) {
                $service->cycle($lecteur);
                if ($lecteur->derniere_erreur) {
                    $this->error("[{$lecteur->nom}] {$lecteur->derniere_erreur}");
                }
            }

            if (! $this->option('une-fois')) {
                sleep($intervalle);
            }
        } while (! $this->option('une-fois'));

        return self::SUCCESS;
    }
}
