<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Passage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Exports CSV lisibles directement par Excel (séparateur « ; », UTF-8 avec BOM). */
class ExportController extends Controller
{
    public function index(): View
    {
        return view('admin.exports', ['caisses' => Caisse::orderBy('nom')->get()]);
    }

    public function telecharger(Request $request, string $type): StreamedResponse
    {
        $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
            'caisse_id' => ['nullable', 'integer', 'exists:caisses,id'],
        ]);

        $du = ($request->date('du') ?? today()->startOfMonth())->startOfDay();
        $au = ($request->date('au') ?? today())->endOfDay();

        [$entetes, $lignes] = match ($type) {
            'paiements' => $this->paiements($du, $au, $request->integer('caisse_id') ?: null),
            'passages' => $this->passages($du, $au),
            'clients' => $this->clients(),
        };

        $fichier = "gymflow-{$type}-".($type === 'clients' ? today()->format('Y-m-d') : $du->format('Y-m-d').'-au-'.$au->format('Y-m-d')).'.csv';

        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($sortie, $ligne, ';');
            }
            fclose($sortie);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function paiements($du, $au, ?int $caisseId): array
    {
        $lignes = Paiement::with(['client', 'user', 'caisse', 'abonnement.formule', 'lignes', 'pack.formule'])
            ->whereBetween('created_at', [$du, $au])
            ->when($caisseId, fn ($q) => $q->where('caisse_id', $caisseId))
            ->orderBy('created_at')
            ->lazy()
            ->map(fn (Paiement $p) => [
                $p->created_at->format('d/m/Y'), $p->created_at->format('H:i'), $p->numero_recu,
                $p->caisse?->nom, $p->user?->name, $p->client?->nom_complet ?? 'Anonyme',
                Paiement::TYPES[$p->type] ?? $p->type, $p->objet(), Paiement::MODES[$p->mode] ?? $p->mode,
                $p->reference, $p->montant, $p->estAnnule() ? 'Annulé' : 'Valide', $p->motif_annulation,
            ]);

        return [['Date', 'Heure', 'Reçu', 'Caisse', 'Caissière', 'Client', 'Type', 'Objet', 'Mode', 'Référence', 'Montant (FCFA)', 'Statut', 'Motif d’annulation'], $lignes];
    }

    private function passages($du, $au): array
    {
        $lignes = Passage::with(['client', 'salle'])
            ->whereBetween('passe_le', [$du, $au])
            ->orderBy('passe_le')
            ->lazy()
            ->map(fn (Passage $p) => [
                $p->passe_le->format('d/m/Y'), $p->passe_le->format('H:i'), $p->salle?->nom,
                $p->client?->nom_complet ?? '', $p->identification(),
                $p->estAutorise() ? 'Autorisé' : 'Refusé', $p->estAutorise() ? '' : $p->message(),
            ]);

        return [['Date', 'Heure', 'Salle', 'Client', 'Pointage', 'Résultat', 'Motif'], $lignes];
    }

    private function clients(): array
    {
        $lignes = Client::orderBy('nom')->lazy()->map(fn (Client $c) => [
            $c->nom, $c->prenoms, Client::TYPES[$c->type] ?? $c->type, $c->telephone, $c->email,
            $c->date_naissance?->format('d/m/Y'), $c->empreinte_id, $c->carte_id,
            $c->finDesDroits()?->format('d/m/Y'), $c->created_at->format('d/m/Y'),
        ]);

        return [['Nom', 'Prénoms', 'Type', 'Téléphone', 'E-mail', 'Naissance', 'N° empreinte', 'N° carte', 'Droits jusqu’au', 'Inscrit le'], $lignes];
    }
}