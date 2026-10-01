<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::with('categorie')
            ->when($request->input('q'), fn ($q, $texte) => $q->where(fn ($w) => $w
                ->where('nom', 'like', "%{$texte}%")->orWhere('code', 'like', "%{$texte}%")))
            ->when($request->integer('categorie_id'), fn ($q, $id) => $q->where('categorie_id', $id))
            ->orderBy('categorie_id')->orderBy('ordre')->orderBy('nom')
            ->get();

        return view('services.index', [
            'services' => $services,
            'categories' => Categorie::withCount('services')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    public function create(): View
    {
        return view('services.form', [
            'service' => new Service(['actif' => true, 'categorie_id' => request()->integer('categorie_id') ?: null]),
            'categories' => Categorie::orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::create($this->valider($request));

        return redirect()->route('services.index')->with('succes', "Service « {$service->nom} » ajouté à la caisse.");
    }

    public function edit(Service $service): View
    {
        return view('services.form', [
            'service' => $service,
            'categories' => Categorie::orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->valider($request, $service));

        return redirect()->route('services.index')->with('succes', "Service « {$service->nom} » mis à jour.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Les tickets passés gardent le libellé et le prix : on peut supprimer sans perdre l'historique
        $service->delete();

        return redirect()->route('services.index')->with('succes', "Service « {$service->nom} » supprimé.");
    }

    private function valider(Request $request, ?Service $service = null): array
    {
        $data = $request->validate([
            'categorie_id' => ['required', 'exists:categories,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('services', 'code')->ignore($service)],
            'nom' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['code'] = mb_strtoupper($data['code']);
        $data['ordre'] = $data['ordre'] ?? 0;
        $data['actif'] = $request->boolean('actif');

        return $data;
    }
}
