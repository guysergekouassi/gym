@extends('layouts.app')
@section('title', 'Prospects')

@php
    use App\Models\Prospect;
    $classes = ['nouveau' => 'info', 'essai_prevu' => 'warn', 'essai_fait' => 'warn', 'inscrit' => 'ok', 'perdu' => 'off'];
@endphp

@section('content')
<div class="top">
    <div>
        <div class="eyebrow">Personnes intéressées, pas encore inscrites{{ $conversion !== null ? " · {$conversion} % finissent par s'inscrire" : '' }}</div>
        <h1>Prospects</h1>
    </div>
</div>

<div class="seg" role="group" aria-label="Filtrer par statut" style="align-self:flex-start">
    <a href="{{ route('prospects.index') }}" aria-current="{{ $statut ? 'false' : 'true' }}">En cours · {{ ($compte['nouveau'] ?? 0) + ($compte['essai_prevu'] ?? 0) + ($compte['essai_fait'] ?? 0) }}</a>
    @foreach(Prospect::STATUTS as $cle => $libelle)
        <a href="{{ route('prospects.index', ['statut' => $cle]) }}" aria-current="{{ $statut === $cle ? 'true' : 'false' }}">{{ $libelle }} · {{ $compte[$cle] ?? 0 }}</a>
    @endforeach
</div>

<div class="grid g3" style="align-items:start">
    <div class="grid span2">
        @forelse($prospects as $prospect)
            <details class="panel">
                <summary>
                    <span style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        {{ $prospect->nom }}
                        <span class="tag {{ $classes[$prospect->statut] }}">{{ Prospect::STATUTS[$prospect->statut] }}</span>
                        <span class="meta" style="font-weight:500">{{ $prospect->telephone ?? 'pas de téléphone' }}{{ $prospect->essai_le ? ' · essai le '.$prospect->essai_le->format('d/m à H:i') : '' }} · {{ $prospect->source ?? '—' }}</span>
                    </span>
                </summary>
                <div class="inner">
                    <form method="POST" action="{{ route('prospects.update', $prospect) }}" class="grid g2">
                        @csrf @method('PUT')
                        <label class="fld sm" for="nom-{{ $prospect->id }}">Nom<input id="nom-{{ $prospect->id }}" name="nom" value="{{ $prospect->nom }}" required></label>
                        <label class="fld sm" for="tel-{{ $prospect->id }}">Téléphone<input id="tel-{{ $prospect->id }}" name="telephone" value="{{ $prospect->telephone }}"></label>
                        <label class="fld sm" for="statut-{{ $prospect->id }}">Statut
                            <select id="statut-{{ $prospect->id }}" name="statut">@foreach(Prospect::STATUTS as $cle => $libelle)<option value="{{ $cle }}" @selected($prospect->statut === $cle)>{{ $libelle }}</option>@endforeach</select>
                        </label>
                        <label class="fld sm" for="essai-{{ $prospect->id }}">Séance d'essai<input id="essai-{{ $prospect->id }}" type="datetime-local" name="essai_le" value="{{ $prospect->essai_le?->format('Y-m-d\TH:i') }}"></label>
                        <label class="fld sm" for="source-{{ $prospect->id }}">Source
                            <select id="source-{{ $prospect->id }}" name="source"><option value="">—</option>@foreach(Prospect::SOURCES as $s)<option @selected($prospect->source === $s)>{{ $s }}</option>@endforeach</select>
                        </label>
                        <label class="fld sm" for="notes-{{ $prospect->id }}">Notes<input id="notes-{{ $prospect->id }}" name="notes" value="{{ $prospect->notes }}"></label>
                        <div class="actions"><button class="btn sm">Enregistrer</button></div>
                    </form>
                    @if($prospect->statut !== 'inscrit')
                        <form method="POST" action="{{ route('prospects.convertir', $prospect) }}" data-confirmer="Inscrire {{ $prospect->nom }} ?" data-texte="Une fiche client est créée à partir de ce prospect. Vous pourrez la compléter puis encaisser l'abonnement." data-bouton="Créer la fiche">@csrf<button class="btn sm ghost">Inscrire : créer la fiche client</button></form>
                    @elseif($prospect->client)
                        <a href="{{ route('clients.show', $prospect->client) }}">Voir la fiche client →</a>
                    @endif
                </div>
            </details>
        @empty
            <p class="empty">Aucun prospect dans cette liste.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('prospects.store') }}" class="card" id="nouveau">
        @csrf
        <h2>Nouveau prospect</h2>
        <p class="sub" style="margin:0">Quelqu'un demande les tarifs, visite la salle ou veut essayer ? Notez-le pour le relancer.</p>
        <input type="hidden" name="statut" value="nouveau">
        <label class="fld" for="p-nom">Nom *<input id="p-nom" name="nom" required maxlength="150"></label>
        <label class="fld" for="p-tel">Téléphone<input id="p-tel" name="telephone" type="tel"></label>
        <label class="fld" for="p-source">Comment nous a-t-il connus ?
            <select id="p-source" name="source"><option value="">—</option>@foreach(Prospect::SOURCES as $s)<option>{{ $s }}</option>@endforeach</select>
        </label>
        <label class="fld" for="p-essai">Séance d'essai (facultatif)<input id="p-essai" type="datetime-local" name="essai_le"></label>
        <label class="fld" for="p-notes">Notes<input id="p-notes" name="notes" placeholder="ex. veut perdre du poids, dispo le soir"></label>
        <button class="btn">Ajouter</button>
    </form>
</div>
@endsection
