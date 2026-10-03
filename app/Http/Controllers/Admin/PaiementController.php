<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CaisseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Journal des encaissements : contrôle de caisse, export, annulation. */
class PaiementController extends Controller
{
    public function index(Request $request): View
    {
        $filtres = $this->filtres($request);
        $base = $this->requete($filtres);

        $valides = (clone $base)->valides();

        return view('admin.paiements.index', [
            'filtres' => $filtres,
            'paiements' => (clone $base)->with(['client', 'user:id,name', 'abonnement.formule', 'annulePar:id,name'])
                ->latest('id')->paginate(30)->withQueryString(),
            'total' => (clone $valides)->sum('montant'),
            'nombre' => (clone $valides)->count(),
            'annules' => (clone $base)->whereNotNull('annule_le')->count(),
            'parMode' => (clone $valides)->selectRaw('mode, SUM(montant) as total, COUNT(*) as nombre')
                ->groupBy('mode')->orderByDesc('total')->get(),
            'parCaissier' => (clone $valides)->selectRaw('user_id, SUM(montant) as total, COUNT(*) as nombre')
                ->groupBy('user_id')->with('user:id,name')->get(),
            'caissiers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filtres = $this->filtres($request);
        $requete = $this->requete($filtres)->with(['client', 'user:id,name', 'abonnement.formule'])->orderBy('id');
        $nom = sprintf('paiements_%s_%s.csv', $filtres['du']->format('Ymd'), $filtres['au']->format('Ymd'));

        return response()->streamDownload(function () use ($requete) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel

            fputcsv($sortie, ['Reçu', 'Date', 'Client', 'Téléphone', 'Objet', 'Mode', 'Référence', 'Montant', 'Caissière', 'Statut'], ';');

            $requete->chunk(500, function ($paiements) use ($sortie) {
                foreach ($paiements as $p) {
                    fputcsv($sortie, array_map([self::class, 'celluleSure'], [
                        $p->numero_recu,
                        $p->created_at->format('d/m/Y H:i'),
                        $p->client?->nom_complet ?? 'Anonyme',
                        $p->client?->telephone ?? '',
                        $p->abonnement ? 'Abonnement '.$p->abonnement->formule->nom : 'Entrée journalière × '.$p->quantite,
                        Paiement::MODES[$p->mode] ?? $p->mode,
                        $p->reference ?? '',
                        $p->montant,
                        $p->user?->name ?? '',
                        $p->estAnnule() ? 'Annulé' : 'Valide',
                    ]), ';');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function annuler(Request $request, Paiement $paiement, CaisseService $caisse): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], ['motif.required' => 'Indiquez le motif de l\'annulation.']);

        try {
            $caisse->annuler($paiement, $request->user(), $data['motif']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', "Reçu {$paiement->numero_recu} annulé.");
    }

    /**
     * Empêche l'injection de formules quand le CSV est ouvert dans Excel
     * (une cellule commençant par = + - @ serait exécutée).
     */
    public static function celluleSure(mixed $valeur): string|int
    {
        if (is_int($valeur)) {
            return $valeur;
        }

        $valeur = (string) $valeur;

        return preg_match('/^[=+\-@\t\r]/', $valeur) ? "'".$valeur : $valeur;
    }

    private function filtres(Request $request): array
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'mode' => ['nullable', Rule::in(array_keys(Paiement::MODES))],
            'type' => ['nullable', Rule::in(array_keys(Paiement::TYPES))],
        ]);

        return [
            'du' => $request->date('du') ?? today(),
            'au' => $request->date('au') ?? today(),
            'user_id' => $request->integer('user_id') ?: null,
            'mode' => $request->query('mode'),
            'type' => $request->query('type'),
        ];
    }

    private function requete(array $f): Builder
    {
        return Paiement::query()
            ->whereBetween('created_at', [$f['du']->copy()->startOfDay(), $f['au']->copy()->endOfDay()])
            ->when($f['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['mode'], fn ($q, $mode) => $q->where('mode', $mode))
            ->when($f['type'], fn ($q, $type) => $q->where('type', $type));
    }
}
