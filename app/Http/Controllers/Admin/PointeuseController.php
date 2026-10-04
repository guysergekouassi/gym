<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommandePointeuse;
use App\Models\Lecteur;
use App\Services\Hikvision\HikvisionClient;
use App\Services\Hikvision\PointeuseInjoignable;
use App\Services\PointeuseService;
use App\Services\SynchroPointeuseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Pointeuses Hikvision : déclaration, test de connexion, envoi des membres. */
class PointeuseController extends Controller
{
    public function index(): View
    {
        return view('admin.pointeuses.index', [
            'pointeuses' => Lecteur::whereNotNull('adresse_ip')->withCount([
                'commandes as en_attente' => fn ($q) => $q->where('statut', CommandePointeuse::EN_ATTENTE),
                'commandes as en_erreur' => fn ($q) => $q->where('statut', CommandePointeuse::ERREUR),
            ])->withMin(['commandes as attente_depuis' => fn ($q) => $q->where('statut', CommandePointeuse::EN_ATTENTE)], 'created_at')->orderByDesc('actif')->orderBy('nom')->get(),
            'modifiee' => Lecteur::find(request()->integer('modifier')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request, null);

        $lecteur = Lecteur::create($data + [
            'token_hash' => hash('sha256', Str::random(64)), // inutilisé : accès par ISAPI
            'actif' => true,
        ]);

        Log::notice('Pointeuse déclarée', ['lecteur' => $lecteur->id, 'ip' => $lecteur->adresse_ip, 'par' => $request->user()->id]);

        return redirect()->route('admin.pointeuses.index')
            ->with('succes', "Pointeuse « {$lecteur->nom} » enregistrée. Cliquez sur « Tester » pour vérifier la liaison.");
    }

    public function update(Request $request, Lecteur $lecteur): RedirectResponse
    {
        $data = $this->valider($request, $lecteur);
        if (blank($data['mot_de_passe'] ?? null)) {
            unset($data['mot_de_passe']); // vide = on garde l'actuel
        }
        $lecteur->update($data + ['derniere_erreur' => null]);

        return redirect()->route('admin.pointeuses.index')->with('succes', "Pointeuse « {$lecteur->nom} » mise à jour.");
    }

    public function tester(Lecteur $lecteur, PointeuseService $service): RedirectResponse
    {
        try {
            $infos = $service->tester($lecteur);
        } catch (PointeuseInjoignable $e) {
            $lecteur->update(['derniere_erreur' => mb_substr($e->getMessage(), 0, 255)]);

            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', sprintf(
            'Liaison réussie avec « %s » : %s, n° de série %s.',
            $lecteur->nom, $infos['modele'] ?? 'modèle inconnu', $infos['numero_serie'] ?? '—'
        ));
    }

    public function synchroniser(Lecteur $lecteur, SynchroPointeuseService $synchro): RedirectResponse
    {
        abort_unless($lecteur->actif && $lecteur->estPointeuse(), 404);

        $nombre = $synchro->toutSynchroniser($lecteur);

        return back()->with('succes', "{$nombre} membre(s) mis en file pour « {$lecteur->nom} ». Envoi dans quelques secondes : la colonne « Envois » passe à « À jour » toute seule.");
    }

    public function destroy(Lecteur $lecteur): RedirectResponse
    {
        $lecteur->update(['actif' => false]);

        return back()->with('succes', "« {$lecteur->nom} » est désactivée.");
    }

    /** Suppression définitive (les passages déjà enregistrés sont conservés). */
    public function supprimer(Lecteur $lecteur): RedirectResponse
    {
        $nom = $lecteur->nom;
        $lecteur->delete();
        Log::notice('Pointeuse supprimée', ['lecteur' => $nom, 'par' => auth()->id()]);

        return redirect()->route('admin.pointeuses.index')->with('succes', "Pointeuse « {$nom} » supprimée.");
    }

    private function valider(Request $request, ?Lecteur $lecteur): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'adresse_ip' => ['required', 'ip', function ($attribut, $valeur, $echec) {
                if (! HikvisionClient::adresseAutorisee((string) $valeur)) {
                    $echec('L\'adresse doit être une adresse du réseau local (ex. 192.168.50.64).');
                }
            }],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'identifiant' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'mot_de_passe' => [$lecteur ? 'nullable' : 'required', 'string', 'max:64'],
        ], [
            'mot_de_passe.required' => 'Le mot de passe admin de la pointeuse est obligatoire (celui choisi à l\'activation).',
        ]);
    }
}
