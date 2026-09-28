@extends('layouts.app')
@section('title', 'Caisse')

@php
    use App\Models\Paiement;
    use App\Support\Fcfa;
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Caisse</h1>
    <p class="text-sm">Encaissé aujourd'hui : <span class="font-bold">{{ Fcfa::format($totalJour) }}</span></p>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Entrée journalière --}}
    <form method="POST" action="{{ route('caisse.journalier') }}" class="bg-white rounded-xl p-5 shadow-sm space-y-4">
        @csrf
        <h2 class="text-lg font-semibold">Entrée journalière</h2>
        <p class="text-xs text-slate-500">Client facultatif : cherche un client existant, ou saisis nom/téléphone, ou laisse vide pour un passage anonyme.</p>

        <div data-recherche-client class="relative">
            <input type="hidden" name="client_id" data-client-id>
            <input type="search" data-client-q placeholder="Rechercher un client (nom ou téléphone)" autocomplete="off"
                   class="w-full border rounded-lg px-3 py-2">
            <ul data-client-resultats class="hidden absolute z-10 w-full bg-white border rounded-lg mt-1 shadow max-h-60 overflow-auto"></ul>
            <p data-client-choisi class="text-sm text-emerald-700 mt-1"></p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <input type="text" name="nom" value="{{ old('nom') }}" placeholder="Nom (nouveau client)" class="border rounded-lg px-3 py-2">
            <input type="tel" name="telephone" value="{{ old('telephone') }}" placeholder="Téléphone" class="border rounded-lg px-3 py-2">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <label class="text-sm">Montant (FCFA)
                <input type="number" name="montant" min="0" step="100" required
                       value="{{ old('montant', config('salle.tarif_journalier')) }}" class="mt-1 w-full border rounded-lg px-3 py-2">
            </label>
            <label class="text-sm">Mode de paiement
                <select name="mode" required class="mt-1 w-full border rounded-lg px-3 py-2">
                    @foreach(Paiement::MODES as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('mode') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Référence transaction (mobile money)"
               class="w-full border rounded-lg px-3 py-2">

        <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3">
            Encaisser & valider l'entrée
        </button>
    </form>

    {{-- Abonnement --}}
    <form method="POST" action="{{ route('caisse.abonnement') }}" class="bg-white rounded-xl p-5 shadow-sm space-y-4">
        @csrf
        <h2 class="text-lg font-semibold">Abonnement / renouvellement</h2>
        <p class="text-xs text-slate-500">Si le client a déjà un abonnement en cours, le nouveau démarre le lendemain de sa fin.</p>

        <div data-recherche-client class="relative">
            <input type="hidden" name="client_id" data-client-id value="{{ $clientPreselectionne?->id }}" required>
            <input type="search" data-client-q placeholder="Rechercher le client (obligatoire)" autocomplete="off"
                   class="w-full border rounded-lg px-3 py-2">
            <ul data-client-resultats class="hidden absolute z-10 w-full bg-white border rounded-lg mt-1 shadow max-h-60 overflow-auto"></ul>
            <p data-client-choisi class="text-sm text-emerald-700 mt-1">{{ $clientPreselectionne ? '✓ '.$clientPreselectionne->nom_complet : '' }}</p>
            <a href="{{ route('clients.create') }}" class="text-xs text-sky-700 hover:underline">+ Nouveau client</a>
        </div>

        <label class="text-sm block">Formule
            <select name="formule_id" required class="mt-1 w-full border rounded-lg px-3 py-2">
                @foreach($formules as $formule)
                    <option value="{{ $formule->id }}" @selected((int) old('formule_id') === $formule->id)>
                        {{ $formule->nom }} — {{ $formule->duree_jours }} j — {{ Fcfa::format($formule->prix) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="text-sm block">Mode de paiement
            <select name="mode" required class="mt-1 w-full border rounded-lg px-3 py-2">
                @foreach(Paiement::MODES as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <input type="text" name="reference" placeholder="Référence transaction (mobile money)" class="w-full border rounded-lg px-3 py-2">

        <button class="w-full rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold py-3">
            Encaisser l'abonnement
        </button>
    </form>
</div>

{{-- Derniers encaissements --}}
<section class="bg-white rounded-xl p-5 shadow-sm mt-6">
    <h2 class="font-semibold mb-3">Derniers encaissements du jour</h2>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-500">
        <tr><th class="py-1">Reçu</th><th>Heure</th><th>Client</th><th>Objet</th><th>Mode</th><th class="text-right">Montant</th></tr>
        </thead>
        <tbody>
        @forelse($paiements as $paiement)
            <tr class="border-t">
                <td class="py-1.5"><a href="{{ route('recus.show', $paiement) }}" class="text-sky-700 hover:underline">{{ $paiement->numero_recu }}</a></td>
                <td>{{ $paiement->created_at->format('H:i') }}</td>
                <td>{{ $paiement->client?->nom_complet ?? 'Anonyme' }}</td>
                <td>{{ $paiement->abonnement ? 'Abonnement '.$paiement->abonnement->formule->nom : 'Journalier' }}</td>
                <td>{{ Paiement::MODES[$paiement->mode] ?? $paiement->mode }}</td>
                <td class="text-right font-medium">{{ Fcfa::format($paiement->montant) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-2 text-slate-400">Aucun encaissement aujourd'hui</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-recherche-client]').forEach((bloc) => {
    const champ = bloc.querySelector('[data-client-q]');
    const idCache = bloc.querySelector('[data-client-id]');
    const liste = bloc.querySelector('[data-client-resultats]');
    const choisi = bloc.querySelector('[data-client-choisi]');
    let minuteur = null;

    champ.addEventListener('input', () => {
        clearTimeout(minuteur);
        idCache.value = '';
        choisi.textContent = '';
        const q = champ.value.trim();
        if (q.length < 2) { liste.classList.add('hidden'); return; }

        minuteur = setTimeout(async () => {
            try {
                const reponse = await fetch(`{{ route('caisse.clients') }}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
                const clients = await reponse.json();

                liste.innerHTML = '';
                if (clients.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-2 text-slate-400 text-sm';
                    li.textContent = 'Aucun client trouvé';
                    liste.appendChild(li);
                }
                clients.forEach((c) => {
                    const li = document.createElement('li');
                    const bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'w-full text-left px-3 py-2 hover:bg-slate-100 text-sm';
                    bouton.textContent = `${c.nom} · ${c.type}${c.telephone ? ' · ' + c.telephone : ''}${c.fin_droits ? ' · jusqu’au ' + c.fin_droits : ''}`;
                    bouton.addEventListener('click', () => {
                        idCache.value = c.id;
                        champ.value = '';
                        choisi.textContent = `✓ ${c.nom}`;
                        liste.classList.add('hidden');
                    });
                    li.appendChild(bouton);
                    liste.appendChild(li);
                });
                liste.classList.remove('hidden');
            } catch (erreur) {
                console.error('Recherche client impossible :', erreur);
                liste.classList.add('hidden');
            }
        }, 250);
    });
});
</script>
@endpush
