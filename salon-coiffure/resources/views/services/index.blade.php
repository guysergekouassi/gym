@php use App\Support\Fcfa; @endphp
@extends('layouts.app')

@section('titre', 'Services & prix')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'ciseaux']) Catalogue des services</h2>
        <p>{{ $services->where('actif', true)->count() }} service(s) actif(s) affiché(s) en caisse</p>
    </div>
    <a class="btn btn-primaire" href="{{ route('services.create') }}">@include('partials.icone', ['nom' => 'plus']) Ajouter un service</a>
</div>

@if($errors->any())<div class="alerte alerte-erreur">{{ $errors->first() }}</div>@endif

<div class="grille-dash">
    <div class="carte">
        <form method="GET" class="carte-titre" style="gap:10px">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un service…">
            <select name="categorie_id" style="width:auto" onchange="this.form.submit()">
                <option value="">Toutes les catégories</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie_id') == $c->id)>{{ $c->nom }}</option>@endforeach
            </select>
            <button class="btn btn-petit" type="submit">Filtrer</button>
        </form>
        @if($services->isEmpty())
            <div class="vide">Aucun service.</div>
        @else
        <div style="overflow-x:auto">
        <table class="tableau">
            <thead><tr><th>Code</th><th>Service</th><th>Catégorie</th><th class="right">Prix</th><th>Caisse</th><th class="right"></th></tr></thead>
            <tbody>
            @foreach($services as $service)
                <tr>
                    <td class="mono small">{{ $service->code }}</td>
                    <td><b>{{ $service->nom }}</b></td>
                    <td><span class="pastille" style="background:{{ $service->categorie->couleur }}"></span>{{ $service->categorie->nom }}</td>
                    <td class="right nowrap" style="color:var(--vert);font-weight:800">{{ Fcfa::format($service->prix) }}</td>
                    <td>@if($service->actif)<span class="badge badge-vert">Visible</span>@else<span class="badge badge-gris">Masqué</span>@endif</td>
                    <td class="right nowrap">
                        <a class="btn btn-petit" href="{{ route('services.edit', $service) }}">@include('partials.icone', ['nom' => 'crayon']) Modifier</a>
                        <form method="POST" action="{{ route('services.destroy', $service) }}" style="display:inline" onsubmit="return confirm('Supprimer « {{ addslashes($service->nom) }} » ? Les anciens tickets restent intacts.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-petit btn-rouge" type="submit" aria-label="Supprimer">@include('partials.icone', ['nom' => 'poubelle'])</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>Catégories</h3></div>
        <div class="carte-corps">
            @foreach($categories as $c)
                <form method="POST" action="{{ route('categories.update', $c) }}" class="filtres-dates" style="margin-bottom:10px;flex-wrap:nowrap">
                    @csrf @method('PUT')
                    <input type="color" name="couleur" value="{{ $c->couleur }}" aria-label="Couleur">
                    <input type="text" name="nom" value="{{ $c->nom }}" required>
                    <input type="number" name="ordre" value="{{ $c->ordre }}" style="width:64px" title="Ordre d'affichage">
                    <button class="btn btn-petit" type="submit" title="Enregistrer">✓</button>
                    <button class="btn btn-petit btn-rouge" type="submit" form="suppr-cat-{{ $c->id }}" title="Supprimer" @disabled($c->services_count > 0)>✕</button>
                </form>
                <form id="suppr-cat-{{ $c->id }}" method="POST" action="{{ route('categories.destroy', $c) }}" onsubmit="return confirm('Supprimer la catégorie ?')">@csrf @method('DELETE')</form>
            @endforeach

            <h3 style="font-size:15px;margin:20px 0 10px">Nouvelle catégorie</h3>
            <form method="POST" action="{{ route('categories.store') }}" class="filtres-dates" style="flex-wrap:nowrap">
                @csrf
                <input type="color" name="couleur" value="#0ea5e9" aria-label="Couleur">
                <input type="text" name="nom" placeholder="Ex. Maquillage" required>
                <button class="btn btn-petit btn-primaire" type="submit">Ajouter</button>
            </form>
        </div>
    </div>
</div>
@endsection
