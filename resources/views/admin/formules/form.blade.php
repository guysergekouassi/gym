@extends('layouts.app')
@section('title', $formule->exists ? 'Modifier la formule' : 'Nouvelle formule')

@php use App\Models\Formule; @endphp

@section('content')
<div class="top"><div><div class="eyebrow"><a href="{{ route('admin.formules.index') }}">← Formules</a></div><h1>{{ $formule->exists ? $formule->nom : 'Nouvelle formule' }}</h1></div></div>

<form method="POST" action="{{ $formule->exists ? route('admin.formules.update', $formule) : route('admin.formules.store') }}" class="card form-card" data-formule>
    @csrf
    @if($formule->exists) @method('PUT') @endif
    <div class="grid g2">
        <label class="fld" for="nom">Nom *<input id="nom" name="nom" value="{{ old('nom', $formule->nom) }}" required maxlength="100" placeholder="ex. Mensuel étudiant"></label>
        <label class="fld" for="type">Type *
            <select id="type" name="type" required data-type>@foreach(Formule::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type', $formule->type) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="fld" for="categorie">Catégorie de tarif
            <select id="categorie" name="categorie">@foreach(Formule::CATEGORIES as $c)<option @selected(old('categorie', $formule->categorie) === $c)>{{ $c }}</option>@endforeach</select>
        </label>
        <label class="fld" for="prix">Prix (FCFA) *<input id="prix" type="number" name="prix" min="0" step="500" value="{{ old('prix', $formule->prix) }}" required></label>
        <label class="fld" for="duree_jours">Durée de validité (jours) *<input id="duree_jours" type="number" name="duree_jours" min="1" max="1095" value="{{ old('duree_jours', $formule->duree_jours) }}" required><span class="aide" data-aide-duree></span></label>
        <label class="fld" for="nb_entrees" data-si="carnet">Nombre d'entrées du carnet *<input id="nb_entrees" type="number" name="nb_entrees" min="1" value="{{ old('nb_entrees', $formule->nb_entrees) }}"></label>
        <label class="fld" for="nb_seances" data-si="coaching">Nombre de séances de coaching *<input id="nb_seances" type="number" name="nb_seances" min="1" value="{{ old('nb_seances', $formule->nb_seances) }}"></label>
    </div>
    <label class="fld" for="description">Description courte<input id="description" name="description" value="{{ old('description', $formule->description) }}" maxlength="255" placeholder="ex. sur présentation de la carte d'étudiant"></label>
    <label class="check" for="actif"><input id="actif" type="checkbox" name="actif" value="1" @checked(old('actif', $formule->actif))> En vente à la caisse</label>
    <div class="actions"><button class="btn">{{ $formule->exists ? 'Enregistrer' : 'Créer la formule' }}</button><a href="{{ route('admin.formules.index') }}" class="btn ghost">Annuler</a></div>
</form>
@endsection

@push('scripts')
<script data-modal-script>
(() => {
    const f = document.querySelector('[data-formule]'), type = f.querySelector('[data-type]');
    const aides = { abonnement: 'Accès illimité pendant cette durée.', carnet: 'Le carnet expire après cette durée, même s’il reste des entrées.', coaching: 'Les séances doivent être faites dans ce délai.' };
    const maj = () => {
        f.querySelectorAll('[data-si]').forEach((l) => { const on = l.dataset.si === type.value; l.hidden = !on; l.querySelector('input').required = on; });
        f.querySelector('[data-aide-duree]').textContent = aides[type.value];
    };
    type.addEventListener('change', maj); maj();
})();
</script>
@endpush
