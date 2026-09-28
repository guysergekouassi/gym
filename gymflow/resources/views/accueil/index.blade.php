<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accueil · {{ config('salle.nom') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex flex-col">
<header class="px-8 py-4 flex justify-between items-center">
    <span class="text-2xl font-bold">{{ config('salle.nom') }}</span>
    <span id="horloge" class="text-2xl tabular-nums"></span>
</header>

<main class="flex-1 flex items-center justify-center p-8">
    <div id="attente" class="text-center text-slate-400">
        <p class="text-4xl">Posez votre doigt sur le lecteur</p>
    </div>

    <div id="fiche" class="hidden w-full max-w-3xl rounded-3xl p-10 text-center transition-colors" role="status" aria-live="assertive">
        <img id="photo" alt="" class="hidden mx-auto h-40 w-40 rounded-full object-cover border-4 border-white mb-6">
        <p id="message" class="text-5xl font-bold mb-4"></p>
        <p id="nom" class="text-4xl mb-2"></p>
        <p id="type" class="text-xl opacity-80"></p>
        <p id="droits" class="text-2xl mt-6"></p>
    </div>
</main>

<script>
const URL_DERNIER = @json(route('accueil.dernier'));
const DUREE_AFFICHAGE_MS = 8000;
let dernierId = null;
let minuteurMasquage = null;

const el = (id) => document.getElementById(id);

function horloge() {
    el('horloge').textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function afficher(p) {
    const fiche = el('fiche');
    fiche.classList.remove('hidden', 'bg-emerald-600', 'bg-red-600');
    fiche.classList.add(p.autorise ? 'bg-emerald-600' : 'bg-red-600');
    el('attente').classList.add('hidden');

    el('message').textContent = p.autorise ? 'Bienvenue !' : p.message;
    el('nom').textContent = p.client ? p.client.nom : '';
    el('type').textContent = p.client ? p.client.type : '';

    if (p.fin_droits) {
        el('droits').textContent = `Abonnement valide jusqu'au ${p.fin_droits} (${p.jours_restants} jour(s) restant(s))`;
    } else {
        el('droits').textContent = p.methode === 'caisse' ? 'Entrée journalière' : '';
    }

    const photo = el('photo');
    if (p.client && p.client.photo_url) {
        photo.src = p.client.photo_url;
        photo.classList.remove('hidden');
    } else {
        photo.classList.add('hidden');
    }

    clearTimeout(minuteurMasquage);
    minuteurMasquage = setTimeout(() => {
        fiche.classList.add('hidden');
        el('attente').classList.remove('hidden');
    }, DUREE_AFFICHAGE_MS);
}

async function interroger() {
    try {
        const reponse = await fetch(URL_DERNIER, { headers: { 'Accept': 'application/json' } });
        if (reponse.status === 401 || reponse.status === 419) { window.location.reload(); return; }
        if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
        const passage = await reponse.json();

        if (passage && passage.id !== dernierId) {
            const premierChargement = dernierId === null;
            dernierId = passage.id;
            // Au premier chargement, on n'affiche que si le passage est très récent
            if (!premierChargement || passage.il_y_a_secondes < 10) afficher(passage);
        }
    } catch (erreur) {
        console.error('Écran d’accueil : lecture impossible', erreur);
    } finally {
        setTimeout(interroger, 1500);
    }
}

horloge();
setInterval(horloge, 10000);
interroger();
</script>
</body>
</html>
