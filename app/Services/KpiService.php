<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Passage;
use Carbon\CarbonInterface;
use App\Models\Formule;
use App\Models\User;
use App\Support\Periode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class KpiService
{
    /** Chiffres du jour : entrées, refus, recettes, affluence par heure. */
    public function resumeJour(): array
    {
        $jour = today()->toDateString();

        $passages = Passage::whereDate('passe_le', $jour)->get(['statut', 'sens', 'passe_le'])
            ->reject(fn ($p) => in_array($p->sens, [Passage::SENS_DEPART, Passage::SENS_DEJA], true));
        $paiements = Paiement::with('user:id,name')->valides()->whereDate('created_at', $jour)->get();

        $affluence = array_fill(0, 24, 0);
        foreach ($passages->where('statut', Passage::STATUT_AUTORISE) as $passage) {
            $affluence[(int) $passage->passe_le->format('G')]++;
        }

        return [
            'entrees' => $passages->where('statut', Passage::STATUT_AUTORISE)->count(),
            'refus' => $passages->where('statut', Passage::STATUT_REFUSE)->count(),
            'recette_journaliers' => $paiements->whereIn('type', Paiement::TYPES_PASSAGE)->sum('montant'),
            'recette_abonnements' => $paiements->where('type', Paiement::TYPE_ABONNEMENT)->sum('montant'),
            'recette_par_caissier' => $paiements->groupBy('user_id')->map(fn (Collection $groupe) => [
                'nom' => $groupe->first()->user?->name ?? '—',
                'nombre' => $groupe->count(),
                'total' => $groupe->sum('montant'),
            ])->values(),
            'recette_par_mode' => $paiements->groupBy('mode')->map->sum('montant')->sortDesc(),
            'affluence' => $affluence,
        ];
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
                ->venues()
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

    /**
     * Recettes des N derniers jours, séparées abonnements / passages.
     * Avec $caissier : uniquement ses encaissements.
     *
     * @return list<array{jour: CarbonImmutable, abonnement: int, journalier: int}>
     */
    public function recettesParJour(int $jours = 7, ?User $caissier = null): array
    {
        $debut = CarbonImmutable::today()->subDays($jours - 1);

        $paiements = Paiement::valides()
            ->where('created_at', '>=', $debut)
            ->when($caissier, fn ($q) => $q->where('user_id', $caissier->id))
            ->get(['type', 'montant', 'created_at']);

        return collect(range(0, $jours - 1))->map(function (int $i) use ($debut, $paiements) {
            $jour = $debut->addDays($i);
            $duJour = $paiements->filter(fn ($p) => $p->created_at->isSameDay($jour));

            return [
                'jour' => $jour,
                'abonnement' => (int) $duJour->where('type', Paiement::TYPE_ABONNEMENT)->sum('montant'),
                'journalier' => (int) $duJour->whereIn('type', Paiement::TYPES_PASSAGE)->sum('montant'),
            ];
        })->all();
    }

    /** Variation en % entre deux valeurs (null si la référence est nulle). */
    public static function variation(int|float $actuel, int|float $reference): ?float
    {
        return $reference > 0 ? round(100 * ($actuel - $reference) / $reference) : null;
    }

    /** Chiffres d'un jour pour la caisse (un caissier, ou toute la salle). */
    public function chiffresCaisse(CarbonInterface $jour, ?User $caissier = null, ?CarbonInterface $jusqua = null): array
    {
        $bornes = [$jour->copy()->startOfDay(), $jusqua ?? $jour->copy()->endOfDay()];
        $paiements = Paiement::valides()
            ->whereBetween('created_at', $bornes)
            ->when($caissier, fn ($q) => $q->where('user_id', $caissier->id))
            ->with('abonnement:id,est_renouvellement')
            ->get();

        $abonnements = $paiements->where('type', Paiement::TYPE_ABONNEMENT);

        return [
            'recette' => (int) $paiements->sum('montant'),
            'tickets' => $paiements->count(),
            'passages_vendus' => (int) $paiements->whereIn('type', Paiement::TYPES_PASSAGE)->sum('quantite'),
            'abonnements' => $abonnements->filter(fn ($p) => ! $p->abonnement?->est_renouvellement)->count(),
            'renouvellements' => $abonnements->filter(fn ($p) => $p->abonnement?->est_renouvellement)->count(),
            'entrees' => Passage::whereBetween('passe_le', $bornes)->venues()->count(),
        ];
    }

    /**
     * Chiffres clés d'une période. $jusqua coupe la période à un instant précis :
     * on compare ainsi « aujourd'hui jusqu'à 17 h » à « hier jusqu'à 17 h », et non à toute la journée d'hier.
     */
    /**
     * Chiffre d'affaires cumulé du mois de la période affichée : du 1er jusqu'à la fin du jour
     * (ou de la semaine) choisi, sans dépasser maintenant. Comparé au mois précédent au même moment.
     *
     * @return array{libelle: string, montant: int, precedent: int, reference: string}
     */
    public function chiffreAffairesMois(Periode $p): array
    {
        $debut = $p->dateReference()->startOfMonth();
        $finMois = $debut->endOfMonth();
        $fin = $p->au->endOfDay()->min(CarbonImmutable::now())->min($finMois);
        $moisComplet = $fin->eq($finMois);

        // Mois précédent : même temps écoulé depuis le 1er, sans déborder sur le mois suivant
        $precedent = $debut->subMonthNoOverflow();
        $finPrecedent = $moisComplet
            ? $precedent->endOfMonth()
            : $precedent->addSeconds((int) $debut->diffInSeconds($fin))->min($precedent->endOfMonth());

        $somme = fn (CarbonInterface $du, CarbonInterface $au) => (int) Paiement::valides()->whereBetween('created_at', [$du, $au])->sum('montant');
        $nom = fn (CarbonInterface $d) => mb_strtolower(Periode::MOIS[$d->month]);
        $de = fn (string $mois) => preg_match('/^[aeiouéè]/u', $mois) ? "d'{$mois}" : "de {$mois}";

        return [
            'libelle' => "Chiffre d'affaires ".$de($nom($debut)),
            'montant' => $somme($debut, $fin),
            'precedent' => $somme($precedent, $finPrecedent),
            'reference' => $moisComplet
                ? 'par rapport à '.$nom($precedent)
                : 'par rapport à '.$nom($precedent).' à la même date · cumul du 1er au '.$fin->format('d/m'),
        ];
    }

    public function chiffresPeriode(Periode $p, ?CarbonInterface $jusqua = null): array
    {
        $fin = $p->au->endOfDay();
        $bornes = [$p->du->startOfDay(), $jusqua ? $fin->min($jusqua) : $fin];
        $paiements = Paiement::valides()->whereBetween('created_at', $bornes)->get(['montant']);

        return [
            'clients_inscrits' => Client::whereBetween('created_at', $bornes)->count(),
            'recette' => (int) $paiements->sum('montant'),
            'tickets' => $paiements->count(),
            'entrees' => Passage::whereBetween('passe_le', $bornes)->venues()->count(),
            'abonnements_actifs' => Client::abonnes()
                ->whereHas('abonnements', fn ($q) => $q->enCours($p->dateReference()))->count(),
        ];
    }

    /**
     * Recettes de la période, abonnements et passages séparés.
     * Un jour choisi → les 7 jours qui finissent ce jour-là ; jusqu'à 62 jours → par jour ; au-delà → par mois.
     *
     * @return array{etiquettes: list<string>, abonnement: list<int>, journalier: list<int>, titre: string}
     */
    public function serieRecettes(Periode $p): array
    {
        $du = $p->granularite === 'jour' ? $p->du->subDays(6) : $p->du;
        $au = $p->au;
        $parMois = $du->diffInDays($au) > 62;

        $paiements = Paiement::valides()
            ->whereBetween('created_at', [$du->startOfDay(), $au->endOfDay()])
            ->get(['type', 'montant', 'created_at']);

        $cases = [];
        for ($d = $parMois ? $du->startOfMonth() : $du; $d->lte($au); $d = $parMois ? $d->addMonthNoOverflow() : $d->addDay()) {
            $cle = $d->format($parMois ? 'Y-m' : 'Y-m-d');
            $cases[$cle] = [
                'etiquette' => $parMois ? mb_substr(Periode::MOIS[$d->month], 0, 4).($du->year !== $au->year ? ' '.$d->format('y') : '') : $d->format('d/m'),
                Paiement::TYPE_ABONNEMENT => 0,
                Paiement::TYPE_JOURNALIER => 0,
            ];
        }

        foreach ($paiements as $paiement) {
            $cle = $paiement->created_at->format($parMois ? 'Y-m' : 'Y-m-d');
            // Les carnets Fidélité sont des séances : rangés avec les journaliers
            $type = $paiement->type === Paiement::TYPE_CARNET ? Paiement::TYPE_JOURNALIER : $paiement->type;
            if (isset($cases[$cle][$type])) {
                $cases[$cle][$type] += $paiement->montant;
            }
        }

        return [
            'etiquettes' => array_values(array_column($cases, 'etiquette')),
            'abonnement' => array_values(array_column($cases, Paiement::TYPE_ABONNEMENT)),
            'journalier' => array_values(array_column($cases, Paiement::TYPE_JOURNALIER)),
            'titre' => $p->granularite === 'jour' ? '7 derniers jours' : ($parMois ? 'par mois' : 'par jour'),
        ];
    }

    /**
     * Répartition des clients (comme la maquette) : les deux formules les plus suivies,
     * les clients de passage, puis « Autres » (autres formules, abonnements expirés).
     *
     * @return list<array{libelle: string, valeur: int}>
     */
    public function repartitionClients(?CarbonInterface $date = null): array
    {
        $date ??= today();
        $total = Client::count();

        $parFormule = Abonnement::enCours($date)
            ->join('formules', 'formules.id', '=', 'abonnements.formule_id')
            ->whereIn('abonnements.client_id', Client::abonnes()->select('id'))
            ->selectRaw('formules.nom as nom, COUNT(DISTINCT abonnements.client_id) as clients')
            ->groupBy('formules.nom')
            ->orderByDesc('clients')
            ->get();

        $parts = $parFormule->take(2)
            ->map(fn ($f) => ['libelle' => 'Abonnements '.mb_strtolower($f->nom), 'valeur' => (int) $f->clients])
            ->values()->all();

        $abonnesDesFormulesPrincipales = array_sum(array_column($parts, 'valeur'));
        $passages = Client::journaliers()->count();
        $parts[] = ['libelle' => 'Passages', 'valeur' => $passages];
        $parts[] = ['libelle' => 'Autres', 'valeur' => max(0, $total - $passages - $abonnesDesFormulesPrincipales)];

        return $parts;
    }

    /** Formules les plus suivies (abonnements en cours à la date). */
    public function topFormules(int $limite = 5, ?CarbonInterface $date = null): Collection
    {
        return Formule::withCount(['abonnements as en_cours' => fn ($q) => $q->enCours($date)])
            ->orderByDesc('en_cours')->orderBy('duree_jours')
            ->limit($limite)->get();
    }

    /** Dernières actions dans la salle : nouveaux clients, abonnements, tickets. */
    public function activiteRecente(int $limite = 6): Collection
    {
        $clients = Client::latest('id')->limit($limite)->get()->map(fn (Client $c) => [
            'quand' => $c->created_at, 'type' => 'client', 'titre' => 'Nouveau client', 'detail' => $c->nom_complet, 'client' => $c,
        ]);

        $paiements = Paiement::valides()->with(['client', 'abonnement.formule'])->latest('id')->limit($limite)->get()
            ->map(fn (Paiement $p) => [
                'quand' => $p->created_at,
                'type' => $p->abonnement ? ($p->abonnement->est_renouvellement ? 'renouvellement' : 'abonnement') : 'passage',
                'titre' => $p->abonnement
                    ? ($p->abonnement->est_renouvellement ? 'Renouvellement' : 'Abonnement').' '.$p->abonnement->formule->nom
                    : ($p->estCarnet() ? $p->objet() : 'Ticket passage'.($p->quantite > 1 ? ' × '.$p->quantite : '')),
                'detail' => $p->client?->nom_complet ?? 'Client anonyme',
                'client' => $p->client,
            ]);

        return $clients->concat($paiements)->sortByDesc('quand')->take($limite)->values();
    }
}
