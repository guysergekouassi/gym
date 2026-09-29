@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
    $user = auth()->user();
    $peutAnnuler = ! $paiement->estAnnule() && ($user->isAdmin()
        || ($paiement->caisse_id === $user->caisse_id && $paiement->created_at->isToday() && ! $paiement->caisse?->estClotureeLe()));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $paiement->numero_recu }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; background: #F3F2EE; color: #111; }
        .ticket { width: 72mm; margin: 12px auto; padding: 4mm; background: #fff; position: relative; }
        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .montant { font-size: 20px; font-weight: bold; text-align: center; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td:first-child { width: 26mm; }
        .lignes td:first-child { width: auto; } .lignes td:last-child { text-align: right; white-space: nowrap; }
        .annule { position: absolute; top: 38%; left: 50%; transform: translate(-50%, -50%) rotate(-14deg); border: 4px solid #B42318; color: #B42318; font: bold 28px sans-serif; padding: 4px 14px; letter-spacing: 2px; background: rgba(255,255,255,.85); }
        .panneau { max-width: 420px; margin: 12px auto; font-family: Manrope, "Segoe UI", sans-serif; font-size: 14px; display: flex; flex-direction: column; gap: 10px; padding: 0 16px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
        .actions a, .actions button { display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-radius: 10px; border: 0; cursor: pointer; font: 700 14px Manrope, sans-serif; text-decoration: none; }
        .imprimer { background: #0B7A55; color: #fff; } .wa { background: #1E8E57; color: #fff; } .retour { background: #fff; color: #1B1E24; border: 1.5px solid #1B1E24 !important; }
        .alerte { padding: 10px 12px; border-radius: 10px; font-weight: 600; }
        .annuler-recu { background: transparent !important; color: #B42318; border: 1.5px solid #B42318 !important; }
        summary { cursor: pointer; font-weight: 700; color: #B42318; }
        details form { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
        details input { min-height: 42px; border: 1.5px solid #D6D3CB; border-radius: 9px; padding: 0 12px; font: inherit; }
        details button { min-height: 42px; border: 0; border-radius: 9px; background: #B42318; color: #fff; font: 700 14px Manrope, sans-serif; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; width: auto; }
            .panneau { display: none; }
        }
    </style>
</head>
<body>
<div class="panneau">
    <noscript>
    @if(session('succes'))<div class="alerte" style="background:#E3F3EC;color:#0B6B4B">{{ session('succes') }}</div>@endif
    @if(session('erreur'))<div class="alerte" style="background:#FCE8E6;color:#B42318">{{ session('erreur') }}</div>@endif
    @if($errors->any())<div class="alerte" style="background:#FCE8E6;color:#B42318">{{ $errors->first() }}</div>@endif
    </noscript>
</div>

<div class="ticket">
    @if($paiement->estAnnule())<div class="annule">ANNULÉ</div>@endif
    <div class="centre gras" style="font-size:15px">{{ config('salle.nom') }}</div>
    <div class="centre">{{ config('salle.adresse') }}</div>
    @if(config('salle.telephone'))<div class="centre">Tél : {{ config('salle.telephone') }}</div>@endif
    <div class="sep"></div>

    <table>
        <tr><td>Reçu</td><td class="gras">{{ $paiement->numero_recu }}</td></tr>
        <tr><td>Date</td><td>{{ $paiement->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Client</td><td>{{ $paiement->client?->nom_complet ?? 'Client de passage' }}</td></tr>
        <tr><td>Objet</td><td>{{ $paiement->type === Paiement::TYPE_VENTE ? 'Vente comptoir' : $paiement->objet() }}</td></tr>
        @if($paiement->abonnement)
            <tr><td>Validité</td><td>{{ $paiement->abonnement->date_debut->format('d/m/Y') }} au {{ $paiement->abonnement->date_fin->format('d/m/Y') }}</td></tr>
            @if($paiement->abonnement->entrees_restantes !== null)<tr><td>Entrées</td><td>{{ $paiement->abonnement->formule->nb_entrees }}</td></tr>@endif
            @if($paiement->abonnement->remise)<tr><td>Remise</td><td>-{{ Fcfa::format($paiement->abonnement->remise) }}</td></tr>@endif
            @if($paiement->abonnement->frais_inscription)<tr><td>Inscription</td><td>{{ Fcfa::format($paiement->abonnement->frais_inscription) }}</td></tr>@endif
        @endif
        @if($paiement->pack)
            <tr><td>Séances</td><td>{{ $paiement->pack->seances_total }} · jusqu'au {{ $paiement->pack->expire_le?->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td>Paiement</td><td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</td></tr>
        @if($paiement->reference)<tr><td>Réf.</td><td>{{ $paiement->reference }}</td></tr>@endif
    </table>

    @if($paiement->lignes->isNotEmpty())
        <div class="sep"></div>
        <table class="lignes">
            @foreach($paiement->lignes as $ligne)
                <tr><td>{{ $ligne->quantite }} × {{ $ligne->libelle }}</td><td>{{ Fcfa::format($ligne->quantite * $ligne->prix_unitaire) }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="sep"></div>
    <div class="montant">{{ Fcfa::format($paiement->montant) }}</div>
    <div class="centre">{{ $paiement->caisse?->nom ?? 'Caisse' }} · {{ $paiement->user?->name ?? 'En ligne' }}</div>
    @if($paiement->estAnnule())
        <div class="sep"></div>
        <div class="centre">Annulé le {{ $paiement->annule_le->format('d/m/Y H:i') }} par {{ $paiement->annulePar?->name ?? '—' }}<br>Motif : {{ $paiement->motif_annulation }}</div>
    @endif
    <div class="sep"></div>
    <div class="centre">Merci et bonne séance !</div>
</div>

<div class="panneau">
    <div class="actions">
        <button class="imprimer" onclick="window.print()">Imprimer</button>
        @if($lienWhatsapp)<a class="wa" href="{{ $lienWhatsapp }}" target="_blank" rel="noopener">Envoyer sur WhatsApp</a>@endif
        @if($user->isCaissier())<a class="retour" href="{{ route('caisse.index') }}">Retour à la caisse</a>@else<a class="retour" href="{{ url()->previous() }}">Retour</a>@endif
    </div>

    @if($peutAnnuler)
        <form method="POST" action="{{ route('recus.annuler', $paiement) }}" class="actions"
              data-motif="Annuler le reçu {{ $paiement->numero_recu }} ?"
              data-motif-aide="Le reçu restera visible mais ne comptera plus dans les recettes{{ $paiement->type === 'vente' ? ', et les articles seront remis en stock' : '' }}. Indiquez la raison : elle est visible par l'administrateur."
              data-motif-exemple="ex. erreur de formule, client remboursé"
              data-bouton="Annuler le reçu">
            @csrf
            <input type="hidden" name="motif">
            <button class="annuler-recu">Annuler ce reçu…</button>
        </form>
    @endif
</div>

@include('partials.alertes')
@if(request()->boolean('imprimer') && config('salle.impression.driver') === 'navigateur' && $user->isCaissier() && ! $paiement->estAnnule())
<script>
    window.addEventListener('load', () => window.print());
    window.addEventListener('afterprint', () => { window.location.href = @json(route('caisse.index')); });
</script>
@endif
</body>
</html>
