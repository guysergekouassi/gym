@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
    $largeur = config('salle.impression.largeur_mm');
    $autoImpression = request()->boolean('imprimer') && config('salle.impression.driver') === 'navigateur' && ! $paiement->estAnnule();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Ticket {{ $paiement->numero_recu }}</title>
    @vite(['resources/js/app.js'])
    <style>
        @page { size: {{ $largeur }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', ui-monospace, monospace; font-size: {{ $largeur === 58 ? 11 : 12 }}px; margin: 0; background: #e2e8f0; color: #000; }
        .ticket { width: {{ $largeur - 8 }}mm; margin: 16px auto; padding: 4mm 3mm; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.15); }
        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .titre { font-size: 1.35em; font-weight: bold; letter-spacing: .5px; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .montant { font-size: 1.8em; font-weight: bold; text-align: center; margin: 8px 0; }
        .annule { border: 2px solid #000; text-align: center; font-weight: bold; font-size: 1.3em; padding: 4px; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td:first-child { width: 36%; }
        .actions { display: flex; justify-content: center; gap: 8px; margin: 12px; font-family: system-ui, sans-serif; }
        .actions a, .actions button { padding: 10px 16px; border-radius: 10px; border: 0; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; }
        .imprimer { background: #f97316; color: #fff; }
        .retour { background: #fff; color: #0f172a; box-shadow: inset 0 0 0 1px #cbd5e1; }
        .alerte { max-width: 90mm; margin: 16px auto 0; padding: 10px 12px; border-radius: 10px; font-family: system-ui, sans-serif; font-size: 13px; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; width: auto; box-shadow: none; }
            .actions, .alerte { display: none; }
        }
    </style>
</head>
<body @if($autoImpression) data-recu-auto data-retour="{{ route('caisse.index') }}" @endif>
@if(session('succes'))<div class="alerte" style="background:#d1fae5;color:#065f46">{{ session('succes') }}</div>@endif
@if(session('erreur'))<div class="alerte" style="background:#fee2e2;color:#991b1b">{{ session('erreur') }}</div>@endif

<div class="ticket">
    <div class="centre titre">{{ config('salle.nom') }}</div>
    <div class="centre">{{ config('salle.adresse') }}</div>
    @if(config('salle.telephone'))<div class="centre">Tél : {{ config('salle.telephone') }}</div>@endif
    <div class="sep"></div>

    @if($paiement->estAnnule())
        <div class="annule">*** TICKET ANNULÉ ***</div>
    @endif

    <table>
        <tr><td>Ticket</td><td class="gras">{{ $paiement->numero_recu }}</td></tr>
        <tr><td>Date</td><td>{{ $paiement->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td>{{ $paiement->client?->nom_complet ?? 'Client journalier' }}</td></tr>
        <tr><td>Objet</td><td>{{ $paiement->abonnement ? 'Abonnement '.$paiement->abonnement->formule->nom : 'Entrée journalière' }}</td></tr>
        @if($paiement->abonnement)
            <tr><td>Validité</td><td>{{ $paiement->abonnement->date_debut->format('d/m/Y') }} au {{ $paiement->abonnement->date_fin->format('d/m/Y') }}</td></tr>
        @else
            <tr><td>Valable</td><td>le {{ $paiement->created_at->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td>Paiement</td><td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</td></tr>
        @if($paiement->reference)<tr><td>Réf.</td><td>{{ $paiement->reference }}</td></tr>@endif
    </table>

    <div class="sep"></div>
    <div class="montant">{{ Fcfa::format($paiement->montant) }}</div>
    <div class="centre">Caisse : {{ $paiement->user?->name ?? '—' }}</div>
    <div class="sep"></div>
    <div class="centre">Merci et bonne séance !</div>
    <div class="centre" style="font-size:.85em;margin-top:4px">Ticket à conserver</div>
</div>

<div class="actions">
    <button type="button" class="imprimer" data-imprimer>Imprimer le ticket</button>
    <a class="retour" href="{{ route('caisse.index') }}">Retour à la caisse</a>
</div>
</body>
</html>
