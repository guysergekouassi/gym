<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Paramètres de la salle : nom de l'entreprise et coordonnées imprimées sur le ticket. */
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
        ], [
            'nom.required' => 'Le nom de l\'entreprise est obligatoire.',
            'telephone.regex' => 'Le téléphone ne doit contenir que des chiffres, espaces, +, / ou tirets.',
        ]);

        foreach (Parametre::SALLE as $cle => $champ) {
            Parametre::definir($cle, trim((string) ($data[$champ] ?? '')));
        }

        Log::notice('Paramètres de la salle modifiés', ['par' => $request->user()->id]);

        return back()->with('succes', 'Paramètres enregistrés. Ils apparaissent tout de suite sur l\'application et les tickets.');
    }
}
