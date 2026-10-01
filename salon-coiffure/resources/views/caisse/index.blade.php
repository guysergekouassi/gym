@extends('layouts.app')

@section('titre', 'Nouvelle vente')
@section('classe-body', 'page-caisse')

@section('contenu')
<div class="caisse">
    <section class="catalogue">
        <div class="recherche">
            <div class="champ-recherche">
                @include('partials.icone', ['nom' => 'recherche'])
                <input type="search" id="recherche" placeholder="Rechercher un service (nom ou code)…" autocomplete="off">
            </div>
        </div>

        <div class="pastilles" id="pastilles">
            <button type="button" class="actif" data-categorie="">Tous <span class="muted small">({{ $services->count() }})</span></button>
            @foreach($categories as $categorie)
                <button type="button" data-categorie="{{ $categorie->id }}">
                    <span class="pastille" style="background: {{ $categorie->couleur }}"></span>{{ $categorie->nom }}
                </button>
            @endforeach
        </div>

        <div class="grille-services" id="grille"></div>
        <div class="vide" id="aucun" hidden>Aucun service ne correspond à la recherche.</div>
        @if($services->isEmpty())
            <div class="carte vide">
                Aucun service actif.
                @if(auth()->user()->isGerante())<a href="{{ route('services.create') }}">Ajouter un service</a>@endif
            </div>
        @endif
    </section>

    <aside class="ticket-panneau">
        <div class="ticket-entete">
            <h3>@include('partials.icone', ['nom' => 'ticket']) Ticket</h3>
            <button type="button" class="btn btn-petit" id="vider" hidden>Vider</button>
        </div>

        <div class="ticket-lignes" id="lignes">
            <div class="ticket-vide" id="ticket-vide">
                @include('partials.icone', ['nom' => 'ciseaux'])
                <div><b>Ticket vide</b></div>
                <div class="small">Cliquez sur un service pour l'ajouter.</div>
            </div>
        </div>

        <div class="ticket-pied">
            <div class="total"><span>TOTAL</span><b id="total">0 FCFA</b></div>

            <div class="modes">
                @foreach(config('salon.modes_paiement') as $code => $libelle)
                    <label><input type="radio" name="mode" value="{{ $code }}" @checked($loop->first)><span>{{ $libelle }}</span></label>
                @endforeach
            </div>

            <div id="bloc-especes">
                <div class="especes">
                    <div>
                        <label for="recu">Montant reçu</label>
                        <input type="number" id="recu" min="0" step="500" inputmode="numeric" placeholder="0">
                    </div>
                    <div>
                        <label>Monnaie à rendre</label>
                        <div class="monnaie" id="monnaie">0 FCFA</div>
                    </div>
                </div>
                <div class="billets" id="billets">
                    <button type="button" data-billet="exact">Exact</button>
                    <button type="button" data-billet="2000">2 000</button>
                    <button type="button" data-billet="5000">5 000</button>
                    <button type="button" data-billet="10000">10 000</button>
                    <button type="button" data-billet="20000">20 000</button>
                </div>
            </div>

            <button type="button" class="btn btn-vert encaisser" id="encaisser" disabled>
                @include('partials.icone', ['nom' => 'imprimante']) Encaisser & imprimer
            </button>
            <div class="ticket-message" id="message"></div>
        </div>
    </aside>
</div>

<iframe id="impression" title="Impression du ticket" style="position:absolute;width:0;height:0;border:0;visibility:hidden"></iframe>
@endsection

@push('scripts')
<script>
    window.CAISSE = {
        services: @json($services),
        url: @json(route('caisse.store')),
        csrf: @json(csrf_token()),
    };
</script>
<script src="{{ asset('js/caisse.js') }}?v={{ filemtime(public_path('js/caisse.js')) }}"></script>
@endpush
