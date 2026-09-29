@extends('layouts.app')
@section('title', 'Codes promo')

@section('content')
<div class="top">
    <div><div class="eyebrow"><a href="{{ route('admin.formules.index') }}">← Formules</a> · la caissière saisit le code au moment d'encaisser un abonnement</div><h1>Codes promo</h1></div>
    <a href="{{ route('admin.promos.create') }}" class="btn">@include('partials.icone', ['nom' => 'plus'])Nouveau code</a>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Code</th><th>Remise</th><th>Valable jusqu'au</th><th class="r">Utilisations</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
        @forelse($promos as $promo)
            <tr>
                <td><span class="name" style="font-family:ui-monospace,Consolas,monospace">{{ $promo->code }}</span></td>
                <td><span class="num" style="font-size:18px">{{ $promo->libelle() }}</span></td>
                <td>{{ $promo->expire_le?->format('d/m/Y') ?? 'Sans limite' }}</td>
                <td class="r">{{ $promo->utilisations }}{{ $promo->utilisations_max ? ' / '.$promo->utilisations_max : '' }}</td>
                <td><span class="tag {{ $promo->estUtilisable() ? 'ok' : 'off' }}">{{ $promo->estUtilisable() ? 'Utilisable' : ($promo->actif ? 'Épuisé ou expiré' : 'Désactivé') }}</span></td>
                <td class="r"><a href="{{ route('admin.promos.edit', $promo) }}" class="pill-btn">Modifier</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Aucun code promo. Exemple : RENTREE25 pour -25 % sur les abonnements de septembre.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
