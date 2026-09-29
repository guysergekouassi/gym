<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\CaisseService;
use App\Services\MessageService;
use App\Support\Fcfa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecuController extends Controller
{
    public function show(Paiement $paiement, MessageService $messages): View
    {
        $paiement->load(['client', 'user', 'caisse', 'abonnement.formule', 'lignes', 'pack.formule', 'annulePar']);

        $lienWhatsapp = null;
        if ($paiement->client?->telephone && ! $paiement->estAnnule()) {
            $message = new \App\Models\Message([
                'telephone' => $paiement->client->telephone,
                'contenu' => $messages->rediger(config('salle.messagerie.modeles.recu'), [
                    'prenom' => $paiement->client->appel,
                    'montant' => Fcfa::format($paiement->montant),
                    'objet' => $paiement->objet(),
                    'recu' => $paiement->numero_recu,
                ]),
            ]);
            $lienWhatsapp = $message->lienWhatsapp();
        }

        return view('recus.show', ['paiement' => $paiement, 'lienWhatsapp' => $lienWhatsapp]);
    }

    public function annuler(Request $request, Paiement $paiement, CaisseService $caisse): RedirectResponse
    {
        $data = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']]);

        $caisse->annuler($paiement, $data['motif'], $request->user());

        return back()->with('succes', "Reçu {$paiement->numero_recu} annulé. Il ne compte plus dans les recettes.");
    }
}
