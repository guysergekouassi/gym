<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Comptes des caissières et leur rattachement à une caisse. */
class CaissierController extends Controller
{
    public function index(): View
    {
        return view('admin.caissiers.index', [
            'caissiers' => User::where('role', User::ROLE_CAISSIER)
                ->with('caisse')
                ->withMax('paiements as dernier_encaissement_le', 'created_at')
                ->orderByDesc('actif')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.caissiers.form', [
            'caissier' => new User(['actif' => true]),
            'caisses' => Caisse::actives()->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valider($request);
        $caissier = User::create($data + ['role' => User::ROLE_CAISSIER]);
        Journal::noter('utilisateur.cree', "Compte caissière de {$caissier->name} créé", $caissier);

        return redirect()->route('admin.caissiers.index')->with('succes', "Compte de {$caissier->name} créé.");
    }

    public function edit(User $caissier): View
    {
        abort_unless($caissier->isCaissier(), 404);

        return view('admin.caissiers.form', [
            'caissier' => $caissier,
            'caisses' => Caisse::where('actif', true)->orWhere('id', $caissier->caisse_id)->orderBy('nom')->get(),
        ]);
    }

    public function update(Request $request, User $caissier): RedirectResponse
    {
        abort_unless($caissier->isCaissier(), 404);

        $data = $this->valider($request, $caissier);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $caissier->update($data);
        Journal::noter('utilisateur.modifie', "Compte de {$caissier->name} modifié".(isset($data['password']) ? ' (nouveau mot de passe)' : '').($caissier->actif ? '' : ' · désactivé'), $caissier);

        return redirect()->route('admin.caissiers.index')->with('succes', "Compte de {$caissier->name} mis à jour.");
    }

    private function valider(Request $request, ?User $caissier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($caissier?->id)],
            'password' => [$caissier ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'caisse_id' => ['required', 'integer', Rule::exists('caisses', 'id')],
        ]) + ['actif' => $request->boolean('actif')];
    }
}
