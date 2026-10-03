<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use App\Services\SynchroPointeuseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pointeuses à empreinte : déclaration, état de la liaison, synchronisation des membres. */
class PointeuseController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.pointeuses.index', [
            'pointeuses' => Lecteur::withCount([
                'commandes as en_attente' => fn ($q) => $q->whereIn('statut', [CommandePointeuse::EN_ATTENTE, CommandePointeuse::ENVOYEE]),
                'commandes as en_erreur' => fn ($q) => $q->where('statut', CommandePointeuse::ERREUR),
            ])->orderByDesc('actif')->orderBy('nom')->get(),
            'adresseServeur' => $this->adresseLocale($request),
            'portServeur' => $request->getPort(),
        ]);
    }

    /**
     * Adresse à saisir dans la pointeuse : l'IP du PC sur le réseau local.
     * « localhost » / 127.0.0.1 ne fonctionnent pas depuis l'appareil.
     */
    private function adresseLocale(Request $request): ?string
    {
        $hote = $request->getHost();
        if (filter_var($hote, FILTER_VALIDATE_IP) && ! str_starts_with($hote, '127.')) {
            return $hote;
        }

        $candidates = array_filter(gethostbynamel(gethostname()) ?: [], fn ($ip) => filter_var(
            $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4
        ) && ! str_starts_with($ip, '127.') && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE));

        return array_values($candidates)[0] ?? null;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{4,50}$/', Rule::unique('lecteurs', 'numero_serie')],
        ], ['numero_serie.regex' => 'Le n° de série ne contient que des lettres et des chiffres (voir l\'étiquette au dos de la pointeuse).']);

        [$lecteur, $token] = Lecteur::creerAvecToken($data['nom']);

        if (filled($data['numero_serie'] ?? null)) {
            $lecteur->update(['numero_serie' => $data['numero_serie']]);

            return back()->with('succes', "Pointeuse « {$lecteur->nom} » déclarée. Réglez maintenant son serveur Cloud (voir l'encadré).");
        }

        // Sans n° de série : accès par l'API générique, token affiché une seule fois
        return back()->with('token_lecteur', ['nom' => $lecteur->nom, 'token' => $token]);
    }

    public function synchroniser(Lecteur $lecteur, SynchroPointeuseService $synchro): RedirectResponse
    {
        abort_unless($lecteur->actif && $lecteur->numero_serie, 404);

        $nombre = $synchro->toutSynchroniser($lecteur);

        return back()->with('succes', "{$nombre} membre(s) envoyé(s) à « {$lecteur->nom} ». Ils apparaîtront sur la pointeuse d'ici quelques secondes.");
    }

    /** Pointeuse déplacée ou nouvelle adresse IP : l'adresse sera réapprise au prochain contact. */
    public function reinitialiserIp(Lecteur $lecteur): RedirectResponse
    {
        $lecteur->update(['adresse_ip' => null]);
        Log::notice('Adresse IP de pointeuse réinitialisée', ['lecteur' => $lecteur->id, 'par' => auth()->id()]);

        return back()->with('succes', "Adresse IP de « {$lecteur->nom} » réinitialisée : elle sera mémorisée à sa prochaine connexion.");
    }

    public function destroy(Lecteur $lecteur): RedirectResponse
    {
        $lecteur->update(['actif' => false]);

        return back()->with('succes', "« {$lecteur->nom} » est désactivée : ses envois sont refusés.");
    }
}
