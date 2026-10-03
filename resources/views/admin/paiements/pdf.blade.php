@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Encaissements — {{ config('salle.nom') }}</title>
    <style>
        @page { margin: 18mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0f172a; }
        .entete { border-bottom: 3px solid #16a36a; padding-bottom: 8px; margin-bottom: 12px; }
        .entete h1 { font-size: 18px; margin: 0; color: #0b1d36; }
        .entete p { margin: 2px 0 0; color: #475569; }
        .resume { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .resume td { width: 25%; vertical-align: top; padding: 8px 10px; background: #f3f6fa; border: 3px solid #fff; }
        .resume .chiffre { font-size: 15px; font-weight: bold; color: #0b1d36; }
        .resume .petit { color: #64748b; }
        table.liste { width: 100%; border-collapse: collapse; }
        table.liste th { background: #0b1d36; color: #fff; text-align: left; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; }
        table.liste td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        table.liste tr:nth-child(even) td { background: #f8fafc; }
        .droite { text-align: right; }
        .annule td { color: #94a3b8; text-decoration: line-through; }
        .pied { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="entete">
    <h1>{{ config('salle.nom') }} — Encaissements</h1>
    <p>{{ $filtres['periode']->libelle() }}
        @if($caissier) · Caissière : {{ $caissier }}@endif
        @if($filtres['mode']) · Mode : {{ Paiement::MODES[$filtres['mode']] ?? $filtres['mode'] }}@endif
        @if($filtres['type']) · {{ Paiement::TYPES[$filtres['type']] ?? $filtres['type'] }}@endif
    </p>
    <p>{{ config('salle.adresse') }}@if(config('salle.telephone')) · {{ config('salle.telephone') }}@endif @if(config('salle.email')) · {{ config('salle.email') }}@endif</p>
</div>

<table class="resume">
    <tr>
        <td><div class="petit">Total encaissé</div><div class="chiffre">{{ Fcfa::format($total) }}</div><div class="petit">{{ $nombre }} ticket(s) valide(s)</div></td>
        <td><div class="petit">Tickets annulés</div><div class="chiffre">{{ $annules }}</div><div class="petit">exclus du total</div></td>
        <td><div class="petit">Par mode de paiement</div>
            @forelse($parMode as $l)<div>{{ Paiement::MODES[$l->mode] ?? $l->mode }} ({{ $l->nombre }}) : <b>{{ Fcfa::format($l->total) }}</b></div>@empty<div>—</div>@endforelse
        </td>
        <td><div class="petit">Par caissière</div>
            @forelse($parCaissier as $l)<div>{{ $l->user?->name ?? '—' }} ({{ $l->nombre }}) : <b>{{ Fcfa::format($l->total) }}</b></div>@empty<div>—</div>@endforelse
        </td>
    </tr>
</table>

<table class="liste">
    <thead>
    <tr><th>Ticket</th><th>Date</th><th>Client</th><th>Objet</th><th>Mode</th><th>Référence</th><th>Caissière</th><th class="droite">Montant</th><th>Statut</th></tr>
    </thead>
    <tbody>
    @forelse($paiements as $p)
        <tr class="{{ $p->estAnnule() ? 'annule' : '' }}">
            <td>{{ $p->numero_recu }}</td>
            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $p->client?->nom_complet ?? 'Anonyme' }}</td>
            <td>{{ $p->abonnement ? 'Abonnement '.$p->abonnement->formule->nom : 'Passage'.($p->quantite > 1 ? ' × '.$p->quantite : '') }}</td>
            <td>{{ Paiement::MODES[$p->mode] ?? $p->mode }}</td>
            <td>{{ $p->reference }}</td>
            <td>{{ $p->user?->name ?? '—' }}</td>
            <td class="droite">{{ Fcfa::format($p->montant) }}</td>
            <td>{{ $p->estAnnule() ? 'Annulé' : 'Valide' }}</td>
        </tr>
    @empty
        <tr><td colspan="9" style="text-align:center;padding:14px;color:#94a3b8">Aucun encaissement sur cette période.</td></tr>
    @endforelse
    </tbody>
</table>
@if($tronque)<p style="color:#b45309">Liste limitée aux 3 000 premiers tickets : affinez la période ou utilisez l'export Excel.</p>@endif

<div class="pied">Édité le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }} · {{ config('salle.nom') }}</div>
</body>
</html>
