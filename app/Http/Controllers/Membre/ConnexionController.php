<?php

namespace App\Http\Controllers\Membre;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('membre.connexion', ['envoiAuto' => config('salle.messagerie.driver') === 'whatsapp_cloud']);
    }

    /** Envoie un nouveau lien sur WhatsApp si l'envoi automatique est configuré. */
    public function store(Request $request, MessageService $messages): RedirectResponse
    {
        $data = $request->validate(['telephone' => ['required', 'string', 'max:20']]);
        $telephone = preg_replace('/\s+/', '', $data['telephone']);
        $client = Client::whereRaw("REPLACE(telephone, ' ', '') = ?", [$telephone])->first();
        $auto = config('salle.messagerie.driver') === 'whatsapp_cloud';

        if ($client && $auto) {
            $lien = route('membre.lien', $client->genererJetonMembre());
            $messages->preparer($client, 'lien_membre', $messages->rediger(config('salle.messagerie.modeles.lien_membre'), ['prenom' => $client->appel, 'lien' => $lien]));
        }

        return back()->with('succes', $auto
            ? 'Si ce numéro est celui d’un membre, un lien de connexion vient de partir sur WhatsApp.'
            : 'Demandez votre lien personnel à l’accueil : il vous sera envoyé sur WhatsApp.');
    }

    public function lien(Request $request, string $jeton): RedirectResponse
    {
        $client = Client::parJetonMembre($jeton);

        if (! $client) {
            return redirect()->route('membre.connexion')->withErrors(['telephone' => 'Ce lien n’est plus valable. Demandez-en un nouveau à l’accueil.']);
        }

        $request->session()->regenerate();
        $request->session()->put('membre_id', $client->id);

        return redirect()->route('membre.accueil');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('membre_id');

        return redirect()->route('membre.connexion')->with('succes', 'Vous êtes déconnecté de votre espace.');
    }
}
