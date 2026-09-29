<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Cours;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Réservations des cours collectifs, avec liste d'attente automatique. */
class ReservationService
{
    public function reserver(Cours $cours, Client $client, CarbonInterface $date): Reservation
    {
        if ((int) $date->isoWeekday() !== $cours->jour_semaine || $date->lt(today())) {
            throw ValidationException::withMessages(['date' => "Ce cours n'a pas lieu à cette date."]);
        }

        return DB::transaction(function () use ($cours, $client, $date) {
            $existante = Reservation::where(['cours_id' => $cours->id, 'client_id' => $client->id])->whereDate('date', $date->toDateString())->first();
            if ($existante && $existante->statut !== 'annulee') {
                return $existante;
            }

            $statut = $cours->inscritsLe($date->toDateString()) < $cours->capacite ? 'reservee' : 'attente';

            if ($existante) {
                $existante->update(['statut' => $statut]);

                return $existante;
            }

            return Reservation::create(['cours_id' => $cours->id, 'client_id' => $client->id, 'date' => $date, 'statut' => $statut]);
        });
    }

    /** Annulation : la première personne en attente récupère la place. */
    public function annuler(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $liberait = $reservation->statut === 'reservee';
            $reservation->update(['statut' => 'annulee']);

            if ($liberait) {
                Reservation::where('cours_id', $reservation->cours_id)
                    ->whereDate('date', $reservation->date->toDateString())
                    ->where('statut', 'attente')
                    ->oldest()
                    ->first()
                    ?->update(['statut' => 'reservee']);
            }
        });
    }
}
