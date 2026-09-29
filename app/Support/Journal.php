<?php

namespace App\Support;

use App\Models\Journal as Entree;
use Illuminate\Database\Eloquent\Model;

/** Trace une action sensible dans le journal : Journal::noter('paiement.annule', 'Reçu R… annulé', $paiement). */
class Journal
{
    public const ACTIONS = [
        'paiement.annule' => 'Reçu annulé',
        'caisse.cloturee' => 'Caisse clôturée',
        'client.cree' => 'Client créé',
        'client.modifie' => 'Client modifié',
        'client.archive' => 'Client archivé',
        'abonnement.gele' => 'Abonnement gelé',
        'gel.termine' => 'Gel terminé',
        'utilisateur.cree' => 'Compte créé',
        'utilisateur.modifie' => 'Compte modifié',
        'compte.mot_de_passe' => 'Mot de passe changé',
        'stock.mouvement' => 'Mouvement de stock',
        'lecteur.cree' => 'Lecteur créé',
        'campagne.creee' => 'Campagne créée',
        'membre.lien' => 'Lien espace membre généré',
    ];

    public static function noter(string $action, string $resume, ?Model $sujet = null, array $details = []): void
    {
        Entree::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'sujet_type' => $sujet?->getMorphClass(),
            'sujet_id' => $sujet?->getKey(),
            'resume' => mb_substr($resume, 0, 255),
            'details' => $details ?: null,
        ]);
    }
}
