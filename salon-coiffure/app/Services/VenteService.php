<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenteService
{
    /**
     * Enregistre une vente. Les prix viennent toujours de la base, jamais du navigateur.
     *
     * @param  array<int, array{service_id:int, quantite:int}>  $lignes
     */
    public function enregistrer(User $caissiere, array $lignes, string $modePaiement, ?int $montantRecu = null): Vente
    {
        $quantites = [];
        foreach ($lignes as $ligne) {
            $id = (int) $ligne['service_id'];
            $quantites[$id] = ($quantites[$id] ?? 0) + (int) $ligne['quantite'];
        }

        $services = Service::actifs()->whereIn('id', array_keys($quantites))->get()->keyBy('id');

        if ($services->count() !== count($quantites)) {
            throw ValidationException::withMessages([
                'lignes' => 'Un des services du ticket n\'existe plus ou a été désactivé. Rechargez la caisse.',
            ]);
        }

        $total = 0;
        foreach ($quantites as $id => $quantite) {
            $total += $services[$id]->prix * $quantite;
        }

        if ($modePaiement === 'especes' && $montantRecu !== null && $montantRecu < $total) {
            throw ValidationException::withMessages([
                'montant_recu' => 'Le montant reçu est inférieur au total du ticket.',
            ]);
        }

        return DB::transaction(function () use ($caissiere, $quantites, $services, $total, $modePaiement, $montantRecu) {
            $vente = Vente::create([
                'numero' => $this->prochainNumero(),
                'user_id' => $caissiere->id,
                'total' => $total,
                'mode_paiement' => $modePaiement,
                'montant_recu' => $modePaiement === 'especes' ? ($montantRecu ?? $total) : null,
                'monnaie_rendue' => $modePaiement === 'especes' && $montantRecu !== null ? $montantRecu - $total : 0,
            ]);

            foreach ($quantites as $id => $quantite) {
                $service = $services[$id];
                $vente->lignes()->create([
                    'service_id' => $service->id,
                    'categorie_id' => $service->categorie_id,
                    'libelle' => $service->nom,
                    'prix_unitaire' => $service->prix,
                    'quantite' => $quantite,
                    'total' => $service->prix * $quantite,
                ]);
            }

            return $vente;
        });
    }

    public function annuler(Vente $vente, User $gerante, string $motif): void
    {
        if ($vente->estAnnulee()) {
            throw ValidationException::withMessages(['motif' => 'Ce ticket est déjà annulé.']);
        }

        $vente->update([
            'annulee_at' => now(),
            'annulee_par' => $gerante->id,
            'motif_annulation' => $motif,
        ]);
    }

    /** Numéro de ticket qui repart à 0001 chaque jour : T-20261001-0001 */
    private function prochainNumero(): string
    {
        $prefixe = 'T-'.now()->format('Ymd').'-';

        $dernier = Vente::where('numero', 'like', $prefixe.'%')->max('numero');
        $suivant = $dernier ? ((int) substr($dernier, -4)) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}
