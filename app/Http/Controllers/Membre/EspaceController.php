<?php

namespace App\Http\Controllers\Membre;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Cours;
use App\Models\Passage;
use App\Models\Reservation;
use App\Services\ReservationService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EspaceController extends Controller
{
    public function accueil(Request $request): View
    {
        $client = $this->membre($request);
        $semaine = $client->passages()->where('statut', Passage::STATUT_AUTORISE)
            ->where('passe_le', '>=', today()->startOfWeek())->pluck('passe_le')->map->toDateString()->unique()->count();

        return view('membre.accueil', [
            'client' => $client,
            'finDroits' => $client->finDesDroits(),
            'abonnement' => $client->abonnementActif(),
            'gel' => $client->gelEnCours(),
            'semaine' => $semaine,
            'reservations' => $client->reservations()->with('cours')->whereDate('date', '>=', today()->toDateString())
                ->whereIn('statut', ['reservee', 'attente'])->orderBy('date')->limit(3)->get(),
            'packs' => $client->packs()->where('statut', 'actif')->with('coach')->get(),
        ]);
    }

    public function historique(Request $request): View
    {
        $client = $this->membre($request);

        return view('membre.historique', [
            'client' => $client,
            'passages' => $client->passages()->latest('passe_le')->limit(30)->get(),
            'paiements' => $client->paiements()->valides()->with(['abonnement.formule', 'lignes', 'pack.formule'])->latest()->limit(20)->get(),
        ]);
    }

    public function progression(Request $request): View
    {
        $client = $this->membre($request);

        return view('membre.progression', ['client' => $client, 'mesures' => $client->mesures()->orderBy('date')->get()]);
    }

    /** QR code personnel à présenter au lecteur (contient le code d'accès). */
    public function qr(Request $request): Response
    {
        $client = $this->membre($request);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd())))->writeString($client->code_acces);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function cours(Request $request): View
    {
        $client = $this->membre($request);
        $cours = Cours::with('coach')->where('actif', true)->get();

        // Les séances des 7 prochains jours
        $seances = collect(range(0, 6))->flatMap(function ($i) use ($cours) {
            $jour = today()->addDays($i);

            return $cours->where('jour_semaine', $jour->isoWeekday())->map(fn (Cours $c) => [
                'cours' => $c, 'date' => $jour, 'inscrits' => $c->inscritsLe($jour->toDateString()),
            ]);
        })->sortBy(fn ($s) => $s['date']->toDateString().' '.$s['cours']->heureCourte())->values();

        $mesReservations = $client->reservations()->whereDate('date', '>=', today()->toDateString())
            ->whereIn('statut', ['reservee', 'attente'])->get()
            ->keyBy(fn ($r) => $r->cours_id.'|'.$r->date->toDateString());

        return view('membre.cours', ['client' => $client, 'seances' => $seances, 'mesReservations' => $mesReservations]);
    }

    public function reserver(Request $request, Cours $cours, ReservationService $service): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date', 'after_or_equal:today']]);
        $client = $this->membre($request);

        if (! $client->abonnementActif()) {
            return back()->withErrors(['date' => 'Il faut un abonnement en cours pour réserver un cours.']);
        }

        $reservation = $service->reserver($cours, $client, Carbon::parse($data['date']));

        return back()->with('succes', $reservation->statut === 'attente'
            ? 'Le cours est complet : vous êtes sur liste d’attente. Vous aurez la place si quelqu’un annule.'
            : 'Place réservée. À bientôt !');
    }

    public function annulerReservation(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        abort_unless($reservation->client_id === $this->membre($request)->id, 403);
        $service->annuler($reservation);

        return back()->with('succes', 'Réservation annulée.');
    }

    private function membre(Request $request): Client
    {
        return $request->attributes->get('membre');
    }
}
