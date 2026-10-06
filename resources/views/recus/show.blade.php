@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
    use App\Support\Horaires;
    $seance = Horaires::duJour($paiement->created_at);
    $semaine = $paiement->abonnement ? Horaires::resume() : [];
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
        .ticket { position: relative; overflow: hidden; width: {{ $largeur - 8 }}mm; margin: 16px auto; padding: 4mm 3mm; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.15); }
        .ticket > * { position: relative; z-index: 1; }
        /* Logo en noir : l'imprimante thermique n'imprime pas la couleur */
        .ticket > .logo { display: block; width: 55%; margin: 0 auto 4px; }
        /* Filigrane : pictogramme pâle derrière le contenu (balise <img>, imprimée même sans « graphiques d'arrière-plan ») */
        .ticket > .filigrane { position: absolute; z-index: 0; left: 50%; top: 55%; width: 85%; transform: translate(-50%, -50%) rotate(-18deg); opacity: .08; pointer-events: none; }
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
    <img class="filigrane" src="{{ asset('images/filigrane-ticket.png') }}" alt="">
    <img class="logo" src="{{ asset('images/logo-ticket.png') }}" alt="">
    <div class="centre titre">{{ config('salle.nom') }}</div>
    <div class="centre">{{ config('salle.adresse') }}</div>
    @if(config('salle.telephone'))<div class="centre">Tél : {{ config('salle.telephone') }}</div>@endif
    @if(config('salle.email'))<div class="centre">{{ config('salle.email') }}</div>@endif
    <div class="centre gras" style="margin-top:4px">Arrivée : {{ $paiement->created_at->format('H:i:s') }}</div>
    @if($seance)<div class="centre">Séance du jour : {{ $seance }}</div>@endif
    <div class="sep"></div>

    @if($paiement->estAnnule())
        <div class="annule">*** TICKET ANNULÉ ***</div>
    @endif

    <table>
        <tr><td>Ticket</td><td class="gras">{{ $paiement->numero_recu }}</td></tr>
        <tr><td>Date</td><td>{{ $paiement->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td>{{ $paiement->client?->nom_complet ?? 'Client journalier' }}</td></tr>
        <tr><td>Objet</td><td>{{ $paiement->objet() }}</td></tr>
        @if($paiement->abonnement)
            <tr><td>Validité</td><td>{{ $paiement->abonnement->date_debut->format('d/m/Y') }} au {{ $paiement->abonnement->date_fin->format('d/m/Y') }}</td></tr>
            <tr><td>Accès</td><td>{{ $paiement->abonnement->formule->uneSeanceParJour() ? '1 séance par jour' : 'Illimité' }}</td></tr>
        @elseif($paiement->estCarnet())
            <tr><td>Séances</td><td>{{ $paiement->quantite }} × {{ Fcfa::format(intdiv($paiement->montant, max(1, $paiement->quantite))) }}</td></tr>
            <tr><td>Valable</td><td>jusqu'à la dernière séance</td></tr>
        @else
            <tr><td>Valable</td><td>le {{ $paiement->created_at->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td>Paiement</td><td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</td></tr>
        @if($paiement->reference)<tr><td>Réf.</td><td>{{ $paiement->reference }}</td></tr>@endif
    </table>

    <div class="sep"></div>
    <div class="montant">{{ Fcfa::format($paiement->montant) }}</div>
    <div class="centre">Caisse : {{ $paiement->user?->name ?? '—' }}</div>
    @if($avantages = $paiement->abonnement?->formule?->avantages())
        <div class="sep"></div>
        <div class="centre gras">Vos avantages</div>
        @foreach($avantages as $avantage)<div>- {{ $avantage }}</div>@endforeach
    @endif
    @if($semaine)
        <div class="sep"></div>
        <div class="centre gras">Horaires des séances</div>
        @foreach($semaine as $ligne)<div class="centre">{{ $ligne }}</div>@endforeach
    @endif
    <div class="sep"></div>
    <div class="centre">{{ config('salle.message_recu') ?: 'Merci et bonne séance !' }}</div>
    <div class="centre" style="font-size:.85em;margin-top:4px">Ticket à conserver</div>
</div>

<div class="actions">
    <button type="button" class="imprimer" data-imprimer>Imprimer le ticket</button>
    <a class="retour" href="{{ route('caisse.index') }}">Retour à la caisse</a>
</div>
</body>
</html>
