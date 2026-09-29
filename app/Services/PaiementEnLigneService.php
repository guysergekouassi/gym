<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\Client;
use App\Models\Formule;
use App\Models\PaiementEnLigne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renouvellement payé en Mobile Money depuis l'espace membre, via CinetPay
 * (Orange Money, MTN MoMo, Moov Money, Wave, carte).
 */
class PaiementEnLigneService
{
    public function __construct(private CaisseService $caisse) {}

    public function estActif(): bool
    {
        return (bool) (config('salle.paiement_en_ligne.cinetpay_apikey') && config('salle.paiement_en_ligne.cinetpay_site_id'));
    }

    /** Crée la transaction chez CinetPay et renvoie l'URL de la page de paiement. */
    public function initier(Client $client, Formule $formule): string
    {
        if (! $this->estActif()) {
            throw new RuntimeException('Le paiement en ligne n’est pas configuré.');
        }

        $transaction = PaiementEnLigne::create([
            'transaction_id' => 'GF'.now()->format('ymdHis').strtoupper(Str::random(6)),
            'client_id' => $client->id,
            'formule_id' => $formule->id,
            'montant' => $formule->prix,
        ]);

        $reponse = Http::timeout(20)->post(config('salle.paiement_en_ligne.cinetpay_url').'/payment', [
            'apikey' => config('salle.paiement_en_ligne.cinetpay_apikey'),
            'site_id' => config('salle.paiement_en_ligne.cinetpay_site_id'),
            'transaction_id' => $transaction->transaction_id,
            'amount' => $formule->prix,
            'currency' => 'XOF',
            'description' => 'Abonnement '.$formule->nom,
            'customer_name' => $client->nom,
            'customer_surname' => $client->prenoms ?? $client->nom,
            'customer_phone_number' => $client->telephone,
            'notify_url' => route('paiement.notification'),
            'return_url' => route('membre.paiement.retour', $transaction->transaction_id),
            'channels' => 'ALL',
            'lang' => 'fr',
        ])->json();

        $transaction->update(['reponse' => $reponse]);

        $url = $reponse['data']['payment_url'] ?? null;
        if (! $url) {
            $transaction->update(['statut' => 'refuse']);
            throw new RuntimeException('CinetPay a refusé la demande : '.($reponse['message'] ?? 'réponse inattendue'));
        }

        return $url;
    }

    /**
     * Vérifie la transaction auprès de CinetPay (jamais sur la seule foi de la notification)
     * puis enregistre l'abonnement une seule fois.
     */
    public function verifier(string $transactionId): ?PaiementEnLigne
    {
        $transaction = PaiementEnLigne::where('transaction_id', $transactionId)->first();
        if (! $transaction || $transaction->statut === 'accepte' || ! $this->estActif()) {
            return $transaction;
        }

        $reponse = Http::timeout(20)->post(config('salle.paiement_en_ligne.cinetpay_url').'/payment/check', [
            'apikey' => config('salle.paiement_en_ligne.cinetpay_apikey'),
            'site_id' => config('salle.paiement_en_ligne.cinetpay_site_id'),
            'transaction_id' => $transactionId,
        ])->json();

        $statut = $reponse['data']['status'] ?? null;
        $montant = (int) ($reponse['data']['amount'] ?? 0);

        if ($statut === 'ACCEPTED' && $montant >= $transaction->montant) {
            DB::transaction(function () use ($transaction, $reponse) {
                $transaction->refresh();
                if ($transaction->statut === 'accepte') {
                    return;
                }
                $paiement = $this->caisse->souscrireAbonnement(
                    $transaction->client,
                    $transaction->formule,
                    ['mode' => 'en_ligne', 'reference' => $transaction->transaction_id],
                    null,
                    $this->caisseEnLigne(),
                );
                $transaction->update(['statut' => 'accepte', 'paiement_id' => $paiement->id, 'reponse' => $reponse]);
            });
        } elseif (in_array($statut, ['REFUSED', 'CANCELED'], true)) {
            $transaction->update(['statut' => 'refuse', 'reponse' => $reponse]);
        }

        return $transaction->fresh();
    }

    /** Caisse virtuelle qui regroupe les paiements en ligne (hors tiroir). */
    public function caisseEnLigne(): Caisse
    {
        return Caisse::firstOrCreate(['nom' => 'Paiements en ligne'], ['emplacement' => 'Espace membre', 'actif' => true]);
    }
}
