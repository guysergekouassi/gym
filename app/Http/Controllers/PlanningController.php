<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Cours;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Planning hebdomadaire des cours collectifs, réservations et présences. */
class PlanningController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['semaine' => ['nullable', 'date']]);
        $lundi = ($request->date('semaine') ?? today())->copy()->startOfWeek();

        $cours = Cours::with('coach')->where('actif', true)->orderBy('heure')->get();
        $reservations = Reservation::whereBetween('date', [$lundi->toDateString(), $lundi->copy()->addDays(6)->toDateString()])
            ->whereIn('statut', ['reservee', 'presente', 'attente'])
            ->get()
            ->groupBy(fn ($r) => $r->cours_id.'|'.$r->date->toDateString());

        return view('planning.index', [
            'lundi' => $lundi,
            'jours' => collect(range(0, 6))->map(fn ($i) => $lundi->copy()->addDays($i)),
            'heures' => $cours->map->heureCourte()->unique()->sort()->values(),
            'cours' => $cours,
            'reservations' => $reservations,
        ]);
    }

    public function seance(Cours $cours, string $date): View
    {
        $jour = Carbon::parse($date);
        abort_unless((int) $jour->isoWeekday() === $cours->jour_semaine, 404);

        return view('planning.seance', [
            'cours' => $cours->load('coach'),
            'jour' => $jour,
            'reservations' => $cours->reservations()->with('client')->whereDate('date', $jour->toDateString())
                ->orderByRaw("CASE statut WHEN 'presente' THEN 0 WHEN 'reservee' THEN 1 WHEN 'attente' THEN 2 ELSE 3 END")
                ->oldest()->get(),
        ]);
    }

    public function inscrire(Request $request, Cours $cours, string $date, ReservationService $service): RedirectResponse
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'exists:clients,id']]);
        $reservation = $service->reserver($cours, Client::findOrFail($data['client_id']), Carbon::parse($date));

        return back()->with('succes', $reservation->statut === 'attente' ? 'Cours complet : client ajouté en liste d’attente.' : 'Réservation enregistrée.');
    }

    public function statut(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $data = $request->validate(['statut' => ['required', Rule::in(['presente', 'absente', 'annulee'])]]);

        if ($data['statut'] === 'annulee') {
            $service->annuler($reservation);
        } else {
            $reservation->update(['statut' => $data['statut']]);
        }

        return back()->with('succes', 'Réservation mise à jour.');
    }
}