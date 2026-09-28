@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $paiement->numero_recu }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; background: #f1f5f9; }
        .ticket { width: 72mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .montant { font-size: 20px; font-weight: bold; text-align: center; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td:first-child { width: 26mm; }
        .actions { text-align: center; margin: 12px; font-family: sans-serif; }
        .actions a, .actions button { display: inline-block; margin: 0 4px; padding: 8px 14px; border-radius: 6px; border: 0; cursor: pointer; font-size: 14px; text-decoration: none; }
        .imprimer { background: #059669; color: #fff; }
        .retour { background: #e2e8f0; color: #0f172a; }
        .alerte { max-width: 72mm; margin: 12px auto 0; padding: 8px; border-radius: 6px; font-family: sans-serif; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; width: auto; }
            .actions, .alerte { display: none; }
        }
    </style>
</head>
<body>
@if(session('succes'))<div class="alerte" style="background:#d1fae5;color:#065f46">{{ session('succes') }}</div>@endif
@if(session('erreur'))<div class="alerte" style="background:#fee2e2;color:#991b1b">{{ session('erreur') }}</div>@endif

<div class="ticket">
    <div class="centre gras" style="font-size:15px">{{ config('salle.nom') }}</div>
    <div class="centre">{{ config('salle.adresse') }}</div>
    @if(config('salle.telephone'))<div class="centre">Tél : {{ config('salle.telephone') }}</div>@endif
    <div class="sep"></div>

    <table>
        <tr><td>Reçu</td><td class="gras">{{ $paiement->numero_recu }}</td></tr>
        <tr><td>Date</td><td>{{ $paiement->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td>{{ $paiement->client?->nom_complet ?? 'Client journalier' }}</td></tr>
        <tr><td>Objet</td><td>{{ $paiement->abonnement ? 'Abonnement '.$paiement->abonnement->formule->nom : 'Entrée journalière' }}</td></tr>
        @if($paiement->abonnement)
            <tr><td>Validité</td><td>{{ $paiement->abonnement->date_debut->format('d/m/Y') }} au {{ $paiement->abonnement->date_fin->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td>Paiement</td><td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</td></tr>
        @if($paiement->reference)<tr><td>Réf.</td><td>{{ $paiement->reference }}</td></tr>@endif
    </table>

    <div class="sep"></div>
    <div class="montant">{{ Fcfa::format($paiement->montant) }}</div>
    <div class="centre">Caisse : {{ $paiement->user?->name ?? '—' }}</div>
    <div class="sep"></div>
    <div class="centre">Merci et bonne séance !</div>
</div>

<div class="actions">
    <button class="imprimer" onclick="window.print()">Imprimer</button>
    <a class="retour" href="{{ route('caisse.index') }}">Retour à la caisse</a>
</div>

@if(request()->boolean('imprimer') && config('salle.impression.driver') === 'navigateur')
<script>
    window.addEventListener('load', () => window.print());
    window.addEventListener('afterprint', () => { window.location.href = @json(route('caisse.index')); });
</script>
@endif
</body>
</html>
