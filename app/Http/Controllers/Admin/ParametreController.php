<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Support\Horaires;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Paramètres de la salle : entreprise, coordonnées et horaires des séances imprimés sur le ticket. */
class ParametreController extends Controller
{
    public function edit(): View
    {
        return view('admin.parametres', [
            'salle' => [
                'nom' => config('salle.nom'),
                'adresse' => config('salle.adresse'),
                'telephone' => config('salle.telephone'),
                'email' => config('salle.email'),
                'message_recu' => config('salle.message_recu'),
            ],
            'horaires' => Horaires::tous(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:60'],
            'adresse' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'regex:/^[0-9+() .\/-]{6,40}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'message_recu' => ['nullable', 'string', 'max:120'],
            'horaires' => ['nullable', 'array'],
            'horaires.*.etat' => ['nullable', Rule::in([Horaires::OUVERT, Horaires::FERME])],
            'horaires.*.debut' => ['nullable', Rule::in(Horaires::heures())],
            'horaires.*.fin' => ['nullable', Rule::in(Horaires::heures())],
        ], [
            'nom.required' => 'Le nom de l\'entreprise est obligatoire.',
            'telephone.regex' => 'Le téléphone ne doit contenir que des chiffres, espaces, +, / ou tirets.',
        ]);

        $horaires = $request->has('horaires') ? $this->horaires($data['horaires'] ?? []) : null;

        foreach (Parametre::SALLE as $cle => $champ) {
            Parametre::definir($cle, trim((string) ($data[$champ] ?? '')));
        }

        if ($horaires !== null) {
            Horaires::enregistrer($horaires);
        }

        Log::notice('Paramètres de la salle modifiés', ['par' => $request->user()->id]);

        return back()->with('succes', 'Paramètres enregistrés. Ils apparaissent tout de suite sur l\'application et les tickets.');
    }

    /** Jours renseignés uniquement (« non défini » = supprimé) ; l'heure de fin doit suivre l'heure de début. */
    private function horaires(array $saisie): array
    {
        $horaires = [];

        foreach (Horaires::JOURS as $n => $nom) {
            $jour = $saisie[$n] ?? [];
            $etat = $jour['etat'] ?? null;

            if ($etat === Horaires::FERME) {
                $horaires[$n] = ['etat' => Horaires::FERME];
            } elseif ($etat === Horaires::OUVERT) {
                $debut = $jour['debut'] ?? null;
                $fin = $jour['fin'] ?? null;
                if (! $debut || ! $fin) {
                    throw ValidationException::withMessages(["horaires.{$n}.debut" => "{$nom} : choisissez l'heure de début et l'heure de fin."]);
                }
                if ($fin <= $debut) {
                    throw ValidationException::withMessages(["horaires.{$n}.fin" => "{$nom} : l'heure de fin doit être après l'heure de début."]);
                }
                $horaires[$n] = ['etat' => Horaires::OUVERT, 'debut' => $debut, 'fin' => $fin];
            }
        }

        return $horaires;
    }
}
