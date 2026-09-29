<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Caisse;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Paiement;
use App\Models\Passage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class KpiService
{
    /** Chiffres du jour : entrées, refus, recettes, affluence par heure (toutes salles ou une seule). */
    public function resumeJour(?int $salleId = null): array
    {
        $jour = today()->toDateString();

        $passages = Passage::whereDate('passe_le', $jour)
            ->when($salleId, fn ($q) => $q->where('salle_id', $salleId))
            ->get(['statut', 'passe_le']);
        $paiements = Paiement::valides()->with('user:id,name')->whereDate('created_at', $jour)
            ->when($salleId, fn ($q) => $q->whereHas('caisse', fn ($c) => $c->where('salle_id', $salleId)))
            ->get();

        $affluence = array_fill(0, 24, 0);
        foreach ($passages->where('statut', Passage::STATUT_AUTORISE) as $passage) {
            $affluence[(int) $passage->passe_le->format('G')]++;
        }

        $paiements->load('caisse:id,nom');

        return [
            'entrees' => $passages->where('statut', Passage::STATUT_AUTORISE)->count(),
            'refus' => $passages->where('statut', Passage::STATUT_REFUSE)->count(),
            'journaliers' => $paiements->where('type', Paiement::TYPE_JOURNALIER)->count(),
            'recette_journaliers' => $paiements->where('type', Paiement::TYPE_JOURNALIER)->sum('montant'),
            'recette_abonnements' => $paiements->where('type', Paiement::TYPE_ABONNEMENT)->sum('montant'),
            'recette_ventes' => $paiements->whereIn('type', [Paiement::TYPE_VENTE, Paiement::TYPE_COACHING])->sum('montant'),
            'recette_par_caissier' => $paiements->groupBy('user_id')->map(fn (Collection $groupe) => [
                'nom' => $groupe->first()->user?->name ?? '—',
                'nombre' => $groupe->count(),
                'total' => $groupe->sum('montant'),
            ])->values(),
            'recette_par_caisse' => $paiements->groupBy('caisse_id')->map(fn (Collection $groupe) => [
                'nom' => $groupe->first()->caisse?->nom ?? 'Sans caisse',
                'nombre' => $groupe->count(),
                'total' => $groupe->sum('montant'),
            ])->sortByDesc('total')->values(),
            'recette_par_mode' => $paiements->groupBy('mode')
                ->map(fn (Collection $groupe) => $groupe->sum('montant'))
                ->sortDesc(),
            'affluence' => $affluence,
            'heure_pointe' => max($affluence) > 0 ? array_search(max($affluence), $affluence, true) : null,
        ];
    }

    /**
     * Encaissements d'une caisse sur une journée : ce que la caissière doit remettre à la clôture.
     */
    public function resumeCaisse(Caisse $caisse, ?CarbonInterface $jour = null): array
    {
        $tous = Paiement::with(['client', 'user:id,name', 'abonnement.formule', 'lignes', 'pack.formule', 'annulePar:id,name'])
            ->where('caisse_id', $caisse->id)
            ->whereDate('created_at', ($jour ?? today())->toDateString())
            ->latest()
            ->latest('id')
            ->get();

        $paiements = $tous->reject->estAnnule()->values();
        $especes = $paiements->whereIn('mode', Paiement::MODES_ESPECES);

        return [
            'tous' => $tous,
            'annules' => $tous->filter->estAnnule()->values(),
            'cloture' => $caisse->clotures()->with('user:id,name')->whereDate('jour', ($jour ?? today())->toDateString())->first(),
            'ventes' => $paiements->whereIn('type', [Paiement::TYPE_VENTE, Paiement::TYPE_COACHING])->count(),
            'paiements' => $paiements,
            'total' => $paiements->sum('montant'),
            'nombre' => $paiements->count(),
            'journaliers' => $paiements->where('type', Paiement::TYPE_JOURNALIER)->count(),
            'abonnements' => $paiements->where('type', Paiement::TYPE_ABONNEMENT)->count(),
            'especes' => $especes->sum('montant'),
            'electronique' => $paiements->sum('montant') - $especes->sum('montant'),
            'par_mode' => $paiements->groupBy('mode')->map(fn (Collection $g) => $g->sum('montant'))->sortDesc(),
            'par_caissier' => $paiements->groupBy('user_id')->map(fn (Collection $g) => [
                'nom' => $g->first()->user?->name ?? '—',
                'nombre' => $g->count(),
                'total' => $g->sum('montant'),
            ])->values(),
        ];
    }

    /** Derniers pointages, pour le fil « en direct » du tableau de bord. */
    public function derniersPassages(int $nombre = 7): Collection
    {
        return Passage::with('client')->latest('passe_le')->latest('id')->limit($nombre)->get();
    }

    /** Nombre d'abonnements en cours par formule. */
    public function repartitionFormules(): Collection
    {
        return Formule::withCount(['abonnements as en_cours' => fn ($q) => $q->enCours()])
            ->orderBy('duree_jours')
            ->get();
    }

    /**
     * Accès refusés aujourd'hui qui n'ont pas encore été réglés :
     * pas d'entrée ni de paiement ensuite, ou empreinte toujours inconnue.
     */
    public function refusARegulariser(): Collection
    {
        $refus = Passage::with('client')
            ->where('statut', Passage::STATUT_REFUSE)
            ->whereDate('passe_le', today()->toDateString())
            ->latest('passe_le')
            ->get()
            ->unique(fn (Passage $p) => $p->client_id ? "c{$p->client_id}" : "e{$p->empreinte_id}");

        return $refus->reject(function (Passage $passage) {
            if (! $passage->client_id) {
                return Client::where('empreinte_id', $passage->empreinte_id)->exists();
            }

            return Passage::where('client_id', $passage->client_id)
                ->where('statut', Passage::STATUT_AUTORISE)
                ->where('passe_le', '>', $passage->passe_le)
                ->exists()
                || Paiement::valides()->where('client_id', $passage->client_id)
                    ->where('created_at', '>=', $passage->passe_le)
                    ->exists();
        })->values();
    }

    public function nombreAbonnementsEnCours(): int
    {
        return Client::abonnes()->whereHas('abonnements', fn ($q) => $q->enCours())->count();
    }

    /** Abonnés en cours venus au moins une fois sur les N derniers jours. */
    public function abonnesActifs(): Collection
    {
        $depuis = now()->subDays((int) config('salle.kpi.actif_jours'));

        return Client::abonnes()
            ->whereHas('abonnements', fn ($q) => $q->enCours())
            ->whereHas('passages', fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)->where('passe_le', '>=', $depuis))
            ->withCount(['passages as passages_30j' => fn ($q) => $q
                ->where('statut', Passage::STATUT_AUTORISE)
                ->where('passe_le', '>=', now()->subDays(30))])
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->orderByDesc('passages_30j')
            ->get();
    }

    /** Abonnés qui paient mais ne viennent plus : la liste à relancer. */
    public function abonnesMoinsActifs(): Collection
    {
        $depuis = now()->subDays((int) config('salle.kpi.inactif_jours'));

        return Client::abonnes()
            ->whereHas('abonnements', fn ($q) => $q->enCours())
            ->whereDoesntHave('passages', fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)->where('passe_le', '>=', $depuis))
            ->withMax(['passages as dernier_passage_le' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->orderBy('dernier_passage_le') // "jamais venu" en premier
            ->get();
    }

    /**
     * Taux de renouvellement des abonnements arrivés à échéance sur la période.
     * Renouvelé = un nouvel abonnement démarre au plus tard X jours après la fin.
     */
    public function renouvellement(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $delai = (int) config('salle.kpi.renouvellement_delai_jours');
        $borneFin = $fin->lt(today()) ? $fin->copy() : today()->subDay();

        $echus = Abonnement::with('client')
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_fin', '>=', $debut->toDateString())
            ->whereDate('date_fin', '<=', $borneFin->toDateString())
            ->get();

        $suivants = Abonnement::where('statut', Abonnement::STATUT_ACTIF)
            ->whereIn('client_id', $echus->pluck('client_id')->unique())
            ->get(['client_id', 'date_debut'])
            ->groupBy('client_id');

        $renouveles = collect();
        $perdus = collect();
        $enAttente = collect();

        foreach ($echus as $abonnement) {
            $limite = $abonnement->date_fin->copy()->addDays($delai);

            $aRenouvele = ($suivants->get($abonnement->client_id) ?? collect())
                ->contains(fn (Abonnement $s) => $s->date_debut->gt($abonnement->date_debut) && $s->date_debut->lte($limite));

            if ($aRenouvele) {
                $renouveles->push($abonnement);
            } elseif ($limite->gte(today())) {
                $enAttente->push($abonnement); // encore dans le délai de grâce
            } else {
                $perdus->push($abonnement);
            }
        }

        $decides = $renouveles->count() + $perdus->count();

        return [
            'echus' => $echus->count(),
            'renouveles' => $renouveles->count(),
            'perdus' => $perdus,
            'en_attente' => $enAttente,
            'taux' => $decides > 0 ? round(100 * $renouveles->count() / $decides, 1) : null,
        ];
    }

    /** Clients ayant renouvelé sur la période. */
    public function clientsQuiRenouvellent(CarbonInterface $debut, CarbonInterface $fin): Collection
    {
        return Abonnement::with(['client', 'formule'])
            ->where('est_renouvellement', true)
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereBetween('created_at', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->latest()
            ->get();
    }

    /** Abonnements qui se terminent bientôt et non encore prolongés. */
    public function expirantBientot(): Collection
    {
        $limite = today()->addDays((int) config('salle.kpi.expiration_alerte_jours'));

        return Abonnement::with(['client', 'formule'])
            ->enCours()
            ->whereDate('date_fin', '<=', $limite->toDateString())
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('abonnements as suivant')
                    ->whereColumn('suivant.client_id', 'abonnements.client_id')
                    ->whereColumn('suivant.date_debut', '>', 'abonnements.date_fin')
                    ->where('suivant.statut', Abonnement::STATUT_ACTIF);
            })
            ->orderBy('date_fin')
            ->get();
    }

    /* ---------- Rapports avancés ---------- */

    /** Recettes des N derniers mois, par type de vente. */
    public function revenusParMois(int $mois = 12): Collection
    {
        $debut = today()->startOfMonth()->subMonths($mois - 1);
        $paiements = Paiement::valides()->where('created_at', '>=', $debut)->get(['created_at', 'montant', 'type']);

        return collect(range(0, $mois - 1))->map(function ($i) use ($debut, $paiements) {
            $m = $debut->copy()->addMonths($i);
            $duMois = $paiements->filter(fn ($p) => $p->created_at->format('Y-m') === $m->format('Y-m'));

            return [
                'mois' => $m,
                'total' => $duMois->sum('montant'),
                'abonnements' => $duMois->where('type', Paiement::TYPE_ABONNEMENT)->sum('montant'),
                'journaliers' => $duMois->where('type', Paiement::TYPE_JOURNALIER)->sum('montant'),
                'autres' => $duMois->whereIn('type', [Paiement::TYPE_VENTE, Paiement::TYPE_COACHING])->sum('montant'),
            ];
        });
    }

    /** Ce que les échéances des 30 prochains jours devraient rapporter, au taux de renouvellement observé. */
    public function revenuPrevu(): array
    {
        $echeances = $this->expirantDans(30);
        $potentiel = $echeances->sum(fn (Abonnement $a) => $a->formule->prix);
        $taux = $this->renouvellement(today()->subDays(90), today())['taux'];

        return [
            'echeances' => $echeances->count(),
            'potentiel' => $potentiel,
            'taux' => $taux,
            'prevu' => $taux !== null ? (int) round($potentiel * $taux / 100) : null,
        ];
    }

    /** Abonnements à la durée qui finissent dans N jours, sans suite déjà payée. */
    public function expirantDans(int $jours): Collection
    {
        return Abonnement::with(['client', 'formule'])->duree()->enCours()
            ->whereDate('date_fin', '<=', today()->addDays($jours)->toDateString())
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('abonnements as suivant')
                    ->whereColumn('suivant.client_id', 'abonnements.client_id')
                    ->whereColumn('suivant.date_debut', '>', 'abonnements.date_fin')
                    ->where('suivant.statut', Abonnement::STATUT_ACTIF);
            })
            ->get();
    }

    /** Pour chaque mois d'inscription : nombre de nouveaux abonnés et part encore abonnée aujourd'hui. */
    public function cohortes(int $mois = 6): Collection
    {
        $premiers = Abonnement::where('statut', Abonnement::STATUT_ACTIF)
            ->selectRaw('client_id, MIN(date_debut) as debut')
            ->groupBy('client_id')
            ->get()
            ->map(fn ($l) => ['client_id' => $l->client_id, 'mois' => substr((string) $l->debut, 0, 7)]);

        $actifs = Abonnement::enCours()->pluck('client_id')->unique()->flip();

        return collect(range($mois - 1, 0))->map(function ($i) use ($premiers, $actifs) {
            $m = today()->startOfMonth()->subMonths($i);
            $cohorte = $premiers->where('mois', $m->format('Y-m'));
            $restent = $cohorte->filter(fn ($l) => $actifs->has($l['client_id']))->count();

            return [
                'mois' => $m,
                'inscrits' => $cohorte->count(),
                'restent' => $restent,
                'retention' => $cohorte->count() ? (int) round(100 * $restent / $cohorte->count()) : null,
            ];
        });
    }

    /** Affluence moyenne par jour de semaine et par heure sur 4 semaines (pour repérer les heures creuses). */
    public function carteAffluence(int $jours = 28): array
    {
        $carte = array_fill(1, 7, array_fill(0, 24, 0));
        Passage::where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', today()->subDays($jours))
            ->get(['passe_le'])
            ->each(function ($p) use (&$carte) {
                $carte[(int) $p->passe_le->isoWeekday()][(int) $p->passe_le->format('G')]++;
            });

        $semaines = max(1, intdiv($jours, 7));

        return array_map(fn ($heures) => array_map(fn ($n) => round($n / $semaines, 1), $heures), $carte);
    }}
