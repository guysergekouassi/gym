<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campagne;
use App\Models\Client;
use App\Models\Passage;
use App\Services\MessageService;
use App\Support\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Campagnes WhatsApp ciblées : promotions, retours d'anciens membres, informations. */
class CampagneController extends Controller
{
    public const SEGMENTS = [
        'actifs' => 'Abonnés en cours',
        'inactifs' => 'Abonnés qui ne viennent plus (14 j)',
        'expires' => 'Anciens abonnés partis depuis moins de 3 mois',
        'journaliers' => 'Clients journaliers',
        'anniversaires_mois' => 'Anniversaires du mois',
        'tous' => 'Tous les clients avec un téléphone',
    ];

    public function index(): View
    {
        return view('admin.campagnes.index', [
            'campagnes' => Campagne::with('user:id,name')->withCount([
                'messages as envoyes' => fn ($q) => $q->where('statut', 'envoye'),
                'messages as en_attente' => fn ($q) => $q->where('statut', 'a_envoyer'),
            ])->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.campagnes.form', [
            'segments' => collect(self::SEGMENTS)->map(fn ($libelle, $cle) => ['libelle' => $libelle, 'nombre' => $this->segment($cle)->count()]),
        ]);
    }

    public function store(Request $request, MessageService $messages): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'segment' => ['required', Rule::in(array_keys(self::SEGMENTS))],
            'contenu' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $clients = $this->segment($data['segment'])->get();
        $campagne = Campagne::create($data + ['destinataires' => $clients->count(), 'user_id' => $request->user()->id]);

        foreach ($clients as $client) {
            $messages->preparer($client, 'campagne', $messages->rediger($data['contenu'], $messages->variablesClient($client)), ['campagne_id' => $campagne->id]);
        }

        Journal::noter('campagne.creee', "Campagne « {$campagne->nom} » : {$clients->count()} destinataire(s)", $campagne);

        return redirect()->route('admin.campagnes.index')->with('succes', config('salle.messagerie.driver') === 'manuel'
            ? "{$clients->count()} message(s) préparé(s) : ils sont dans « À faire », prêts à être envoyés."
            : "{$clients->count()} message(s) envoyé(s) ou en cours d'envoi.");
    }

    private function segment(string $cle): Builder
    {
        $avecTelephone = Client::whereNotNull('telephone')->where('telephone', '!=', '');

        return match ($cle) {
            'actifs' => $avecTelephone->abonnes()->whereHas('abonnements', fn ($q) => $q->enCours()),
            'inactifs' => $avecTelephone->abonnes()->whereHas('abonnements', fn ($q) => $q->enCours())
                ->whereDoesntHave('passages', fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)->where('passe_le', '>=', now()->subDays((int) config('salle.kpi.inactif_jours')))),
            'expires' => $avecTelephone->abonnes()->whereDoesntHave('abonnements', fn ($q) => $q->enCours())
                ->whereHas('abonnements', fn ($q) => $q->where('statut', 'actif')->whereDate('date_fin', '>=', today()->subMonths(3)->toDateString())),
            'journaliers' => $avecTelephone->journaliers(),
            'anniversaires_mois' => $avecTelephone->whereNotNull('date_naissance')->whereMonth('date_naissance', today()->month),
            default => $avecTelephone,
        };
    }
}