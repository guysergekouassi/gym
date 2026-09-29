<?php

namespace App\Http\Controllers\Membre;

use App\Http\Controllers\Controller;
use App\Models\Formule;
use App\Models\PaiementEnLigne;
use App\Services\PaiementEnLigneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Renouvellement en ligne depuis l'espace membre. */
class PaiementController extends Controller
{
    public function formules(Request $request, PaiementEnLigneService $enLigne): View
    {
        $client = $request->attributes->get('membre');

        return view('membre.renouveler', [
            'client' => $client,
            'finDroits' => $client->finDesDroits(),
            'formules' => Formule::where('actif', true)->acces()->orderBy('prix')->get(),
            'actif' => $enLigne->estActif(),
        ]);
    }

    public function payer(Request $request, PaiementEnLigneService $enLigne): RedirectResponse
    {
        $data = $request->validate(['formule_id' => ['required', Rule::exists('formules', 'id')->where('actif', true)]]);

        try {
            return redirect()->away($enLigne->initier($request->attributes->get('membre'), Formule::findOrFail($data['formule_id'])));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['formule_id' => 'Le paiement en ligne est indisponible pour le moment. Réessayez plus tard ou payez à l’accueil.']);
        }
    }

    public function retour(Request $request, string $transaction, PaiementEnLigneService $enLigne): View
    {
        $paiement = PaiementEnLigne::where('transaction_id', $transaction)
            ->where('client_id', $request->attributes->get('membre')->id)->firstOrFail();

        try {
            $paiement = $enLigne->verifier($transaction) ?? $paiement;
        } catch (Throwable $e) {
            report($e);
        }

        return view('membre.paiement-retour', ['paiement' => $paiement->load('formule', 'paiement')]);
    }

    /** Appel serveur de CinetPay : on revérifie toujours le statut auprès de CinetPay. */
    public function notification(Request $request, PaiementEnLigneService $enLigne): Response
    {
        $transaction = (string) $request->input('cpm_trans_id', '');

        if ($transaction !== '') {
            try {
                $enLigne->verifier($transaction);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response('OK');
    }
}
