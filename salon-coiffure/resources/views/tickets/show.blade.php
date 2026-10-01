@php
    use App\Support\Fcfa;
    $largeur = config('salon.ticket.largeur_mm') === 58 ? 58 : 80;
    $cadre = request()->boolean('cadre');
    $autoImpression = request()->boolean('imprimer') && config('salon.ticket.driver') === 'navigateur';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $vente->numero }}</title>
    <style>
        @page { size: {{ $largeur }}mm auto; margin: 0; }
        body { font-family: 'Courier New', Consolas, monospace; font-size: {{ $largeur === 58 ? 11 : 12 }}px; margin: 0; background: #f1f5f9; color: #000; }
        .ticket { width: {{ $largeur - 8 }}mm; margin: 12px auto; padding: 3mm 4mm; background: #fff; }
        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .nom-salon { font-size: {{ $largeur === 58 ? 14 : 16 }}px; font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.m { text-align: right; white-space: nowrap; padding-left: 6px; }
        .detail { color: #333; font-size: .92em; }
        .total td { font-size: {{ $largeur === 58 ? 15 : 18 }}px; font-weight: bold; padding: 4px 0; }
        .annule { border: 2px solid #000; text-align: center; font-weight: bold; padding: 4px; margin: 6px 0; }
        .actions { text-align: center; margin: 12px; font-family: 'Segoe UI', sans-serif; }
        .actions a, .actions button { display: inline-block; margin: 0 4px; padding: 10px 16px; border-radius: 8px; border: 0; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; font-family: inherit; }
        .imprimer { background: #059669; color: #fff; }
        .retour { background: #e2e8f0; color: #0f172a; }
        .alerte { max-width: 72mm; margin: 12px auto 0; padding: 8px; border-radius: 6px; font-family: 'Segoe UI', sans-serif; font-size: 13px; }
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
    <div class="centre nom-salon">{{ config('salon.nom') }}</div>
    <div class="centre">{{ config('salon.adresse') }}</div>
    @if(config('salon.telephone'))<div class="centre">Tél : {{ config('salon.telephone') }}</div>@endif
    <div class="sep"></div>

    <table>
        <tr><td>Ticket</td><td class="m gras">{{ $vente->numero }}</td></tr>
        <tr><td>Date</td><td class="m">{{ $vente->created_at->format('d/m/Y H:i') }}</td></tr>
    </table>
    @if($vente->estAnnulee())<div class="annule">TICKET ANNULÉ</div>@endif
    <div class="sep"></div>

    <table>
        @foreach($vente->lignes as $ligne)
            <tr><td colspan="2" class="gras">{{ $ligne->libelle }}</td></tr>
            <tr class="detail">
                <td>&nbsp;&nbsp;{{ $ligne->quantite }} x {{ Fcfa::format($ligne->prix_unitaire) }}</td>
                <td class="m">{{ Fcfa::format($ligne->total) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="sep"></div>

    <table>
        <tr class="total"><td>TOTAL</td><td class="m">{{ Fcfa::format($vente->total) }}</td></tr>
        <tr><td>Paiement</td><td class="m">{{ $vente->libelleModePaiement() }}</td></tr>
        @if($vente->montant_recu !== null)
            <tr><td>Reçu</td><td class="m">{{ Fcfa::format($vente->montant_recu) }}</td></tr>
            <tr><td>Monnaie rendue</td><td class="m">{{ Fcfa::format($vente->monnaie_rendue) }}</td></tr>
        @endif
    </table>
    <div class="sep"></div>
    <div class="centre">Servi par : {{ $vente->user?->name ?? '—' }}</div>
    <div class="centre" style="margin-top:4px">{{ config('salon.message_ticket') }}</div>
</div>

@unless($cadre)
<div class="actions">
    @if(config('salon.ticket.driver') === 'escpos')
        <form method="POST" action="{{ route('tickets.imprimer', $vente) }}" style="display:inline">
            @csrf
            <button class="imprimer" type="submit">Réimprimer</button>
        </form>
    @else
        <button class="imprimer" onclick="window.print()">Imprimer</button>
    @endif
    <a class="retour" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('caisse.index') }}">Retour</a>
</div>
@endunless

@if($autoImpression)
<script>
    window.addEventListener('load', () => window.print());
    @unless($cadre)
    window.addEventListener('afterprint', () => { window.location.href = @json(route('caisse.index')); });
    @endunless
</script>
@endif
</body>
</html>
