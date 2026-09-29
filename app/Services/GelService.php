<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Gel;
use App\Support\Journal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gel d'abonnement : pendant le gel l'accès est refusé,
 * et les jours gelés sont ajoutés à la fin de l'abonnement (et des renouvellements déjà payés).
 */
class GelService
{
    public function geler(Client $client, CarbonInterface $du, int $jours, ?string $motif): Gel
    {
        $abonnement = $client->abonnements()->duree()
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_debut', '<=', $du->toDateString())
            ->whereDate('date_fin', '>=', $du->toDateString())
            ->orderByDesc('date_fin')
            ->first();

        if (! $abonnement) {
            throw ValidationException::withMessages(['du' => "Aucun abonnement à la durée n'est en cours à cette date."]);
        }

        $chevauche = $client->gels()
            ->whereDate('du', '<=', $du->copy()->addDays($jours - 1)->toDateString())
            ->whereDate('au', '>=', $du->toDateString())
            ->exists();
        if ($chevauche) {
            throw ValidationException::withMessages(['du' => 'Un gel existe déjà sur cette période.']);
        }

        return DB::transaction(function () use ($client, $abonnement, $du, $jours, $motif) {
            $this->decaler($client, $abonnement, $jours);

            $gel = Gel::create([
                'abonnement_id' => $abonnement->id,
                'client_id' => $client->id,
                'du' => $du,
                'au' => $du->copy()->addDays($jours - 1),
                'jours' => $jours,
                'motif' => $motif,
                'user_id' => auth()->id(),
            ]);

            Journal::noter('abonnement.gele', "{$client->nom_complet} : gel de {$jours} jour(s) à partir du {$du->format('d/m/Y')}", $gel);

            return $gel;
        });
    }

    /** Fin anticipée : les jours non utilisés sont retirés de la prolongation. */
    public function terminer(Gel $gel): void
    {
        if (today()->gt($gel->au)) {
            throw ValidationException::withMessages(['gel' => 'Ce gel est déjà terminé.']);
        }

        DB::transaction(function () use ($gel) {
            $fin = today()->lt($gel->du) ? $gel->du->copy()->subDay() : today()->subDay();
            $inutilises = (int) $fin->diffInDays($gel->au);

            $this->decaler($gel->client, $gel->abonnement, -$inutilises);

            if ($fin->lt($gel->du)) {
                $gel->delete(); // gel pas encore commencé : on l'annule complètement
            } else {
                $gel->update(['au' => $fin, 'jours' => $gel->jours - $inutilises]);
            }

            Journal::noter('gel.termine', "{$gel->client->nom_complet} : gel terminé, {$inutilises} jour(s) rendus", $gel);
        });
    }

    /** Décale la fin de l'abonnement et les abonnements suivants déjà payés. */
    private function decaler(Client $client, Abonnement $abonnement, int $jours): void
    {
        if ($jours === 0) {
            return;
        }

        $suivants = $client->abonnements()->duree()
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_debut', '>', $abonnement->date_fin->toDateString())
            ->get();

        $abonnement->update(['date_fin' => $abonnement->date_fin->copy()->addDays($jours)]);

        foreach ($suivants as $suivant) {
            $suivant->update([
                'date_debut' => $suivant->date_debut->copy()->addDays($jours),
                'date_fin' => $suivant->date_fin->copy()->addDays($jours),
            ]);
        }
    }
}
