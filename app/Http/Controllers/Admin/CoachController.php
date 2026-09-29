<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coach;
use App\Models\SeanceCoaching;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CoachController extends Controller
{
    public function index(): View
    {
        return view('admin.coachs.index', [
            'coachs' => Coach::withCount(['cours' => fn ($q) => $q->where('actif', true), 'packs as packs_actifs' => fn ($q) => $q->where('statut', 'actif')])
                ->orderByDesc('actif')->orderBy('nom')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.coachs.form', ['coach' => new Coach(['actif' => true, 'commission_pct' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coach::create($this->valider($request));

        return redirect()->route('admin.coachs.index')->with('succes', 'Coach ajouté.');
    }

    public function edit(Coach $coach): View
    {
        return view('admin.coachs.form', ['coach' => $coach]);
    }

    public function update(Request $request, Coach $coach): RedirectResponse
    {
        $coach->update($this->valider($request));

        return redirect()->route('admin.coachs.index')->with('succes', 'Coach mis à jour.');
    }

    /** Séances de coaching faites dans le mois et commissions dues à chaque coach. */
    public function commissions(Request $request): View
    {
        $request->validate(['mois' => ['nullable', 'date_format:Y-m']]);
        $mois = $request->filled('mois') ? Carbon::createFromFormat('Y-m', $request->input('mois'))->startOfMonth() : today()->startOfMonth();

        $seances = SeanceCoaching::with(['pack.client', 'pack.formule', 'coach'])
            ->whereBetween('faite_le', [$mois, $mois->copy()->endOfMonth()])
            ->orderBy('faite_le')
            ->get();

        $parCoach = $seances->groupBy('coach_id')->map(function ($lignes) {
            $coach = $lignes->first()->coach;
            $valeur = $lignes->sum(fn ($s) => $s->pack->prixSeance());

            return [
                'coach' => $coach,
                'seances' => $lignes->count(),
                'valeur' => $valeur,
                'commission' => $coach ? intdiv($valeur * $coach->commission_pct, 100) : 0,
                'detail' => $lignes,
            ];
        })->sortByDesc('valeur')->values();

        return view('admin.coachs.commissions', ['mois' => $mois, 'parCoach' => $parCoach]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'specialite' => ['nullable', 'string', 'max:100'],
            'commission_pct' => ['required', 'integer', 'min:0', 'max:100'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}