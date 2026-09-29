<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodePromo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CodePromoController extends Controller
{
    public function index(): View
    {
        return view('admin.promos.index', ['promos' => CodePromo::orderByDesc('actif')->latest()->get()]);
    }

    public function create(): View
    {
        return view('admin.promos.form', ['promo' => new CodePromo(['type' => 'pourcentage', 'actif' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        CodePromo::create($this->valider($request));

        return redirect()->route('admin.promos.index')->with('succes', 'Code promo créé.');
    }

    public function edit(CodePromo $promo): View
    {
        return view('admin.promos.form', ['promo' => $promo]);
    }

    public function update(Request $request, CodePromo $promo): RedirectResponse
    {
        $promo->update($this->valider($request, $promo));

        return redirect()->route('admin.promos.index')->with('succes', 'Code promo mis à jour.');
    }

    private function valider(Request $request, ?CodePromo $promo = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('codes_promo', 'code')->ignore($promo?->id)],
            'type' => ['required', Rule::in(array_keys(CodePromo::TYPES))],
            'valeur' => ['required', 'integer', 'min:1', $request->input('type') === 'pourcentage' ? 'max:100' : 'max:10000000'],
            'expire_le' => ['nullable', 'date'],
            'utilisations_max' => ['nullable', 'integer', 'min:1'],
        ]) + ['actif' => $request->boolean('actif')];
    }
}