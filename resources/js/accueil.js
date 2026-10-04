// Écran d'accueil (kiosque) : affiche le client qui vient de pointer.
// Deux sources :
//   1. secours : la caissière tape le n° du membre puis Entrée sur ce poste ;
//   2. la pointeuse en réseau envoie chaque passage : l'écran interroge le dernier passage.

const racine = document.querySelector('[data-accueil]');
const URL_DERNIER = racine.dataset.urlDernier;
const URL_SCAN = racine.dataset.urlScan;
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const DUREE_AFFICHAGE_MS = 7000;

const el = (id) => document.getElementById(id);
let dernierId = null;
let minuteurMasquage = null;

function horloge() {
    const maintenant = new Date();
    el('horloge').textContent = maintenant.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    el('date').textContent = maintenant.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
}

// Petit signal sonore généré localement (aucun fichier externe)
let audio = null;
function bip(ok) {
    try {
        audio ??= new AudioContext();
        const osc = audio.createOscillator();
        const gain = audio.createGain();
        osc.frequency.value = ok ? 880 : 220;
        osc.type = ok ? 'sine' : 'square';
        gain.gain.setValueAtTime(0.15, audio.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + (ok ? 0.25 : 0.6));
        osc.connect(gain).connect(audio.destination);
        osc.start();
        osc.stop(audio.currentTime + (ok ? 0.25 : 0.6));
    } catch { /* son indisponible : sans importance */ }
}

function afficher(p) {
    const fiche = el('fiche');
    // 1er badge du jour = arrivée, 2e = départ, ensuite séance déjà enregistrée
    const etats = {
        entree: ['ok', 'Bienvenue !', ''],
        depart: ['depart', 'À bientôt !', p.heure ? `Départ enregistré à ${p.heure}` : ''],
        deja: ['deja', 'Déjà enregistré', p.detail || 'Votre arrivée et votre départ du jour sont déjà enregistrés'],
    };
    const [etat, titre, detail] = p.autorise ? (etats[p.sens] ?? etats.entree) : ['ko', 'Accès refusé', p.message];
    fiche.dataset.etat = etat;
    fiche.classList.remove('hidden');
    el('attente').classList.add('hidden');

    el('message').textContent = titre;
    el('motif').textContent = detail;
    el('nom').textContent = p.client ? p.client.nom : 'Empreinte inconnue';
    el('type').textContent = p.client ? p.client.type : '';

    if (p.fin_droits) {
        el('droits').textContent = `Abonnement valide jusqu'au ${p.fin_droits} · ${p.jours_restants} jour(s) restant(s)`;
    } else {
        el('droits').textContent = p.autorise && p.methode === 'caisse' ? 'Entrée journalière réglée' : '';
    }

    const photo = el('photo');
    const initiale = el('initiale');
    if (p.client && p.client.photo_url) {
        photo.src = p.client.photo_url;
        photo.classList.remove('hidden');
        initiale.classList.add('hidden');
    } else {
        photo.classList.add('hidden');
        photo.removeAttribute('src');
        initiale.textContent = p.client ? p.client.initiale : '?';
        initiale.classList.remove('hidden');
    }

    bip(p.autorise);

    clearTimeout(minuteurMasquage);
    minuteurMasquage = setTimeout(() => {
        fiche.classList.add('hidden');
        el('attente').classList.remove('hidden');
    }, DUREE_AFFICHAGE_MS);
}

async function interroger() {
    try {
        const reponse = await fetch(URL_DERNIER, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (reponse.status === 401 || reponse.status === 419) { window.location.reload(); return; }
        if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
        const passage = await reponse.json();
        el('hors-ligne').classList.add('hidden');

        if (passage && passage.id !== dernierId) {
            const premierChargement = dernierId === null;
            dernierId = passage.id;
            // Au premier chargement, on n'affiche que si le passage est très récent
            if (!premierChargement || passage.il_y_a_secondes < 10) afficher(passage);
        }
    } catch (erreur) {
        el('hors-ligne').classList.remove('hidden');
        console.error('Écran d’accueil : lecture impossible', erreur);
    } finally {
        setTimeout(interroger, 1500);
    }
}

// --- Saisie du n° au clavier (secours si la pointeuse est en panne) ---
// Le n° est tapé puis validé par Entrée.
let tampon = '';
let dernierCaractere = 0;

async function envoyerNumero(numero) {
    try {
        const reponse = await fetch(URL_SCAN, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ empreinte_id: numero }),
        });
        if (reponse.status === 401 || reponse.status === 419) { window.location.reload(); return; }
        if (reponse.status === 422) {
            afficher({ autorise: false, message: 'N° invalide, réessayez', client: null });
            return;
        }
        if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
        const passage = await reponse.json();
        dernierId = passage.id;
        afficher(passage);
    } catch (erreur) {
        console.error('Envoi impossible', erreur);
        el('hors-ligne').classList.remove('hidden');
    }
}

document.addEventListener('keydown', (e) => {
    const maintenant = Date.now();
    if (maintenant - dernierCaractere > 3000) tampon = ''; // saisie abandonnée : on repart à zéro
    dernierCaractere = maintenant;

    if (e.key === 'Enter') {
        const numero = tampon.trim();
        tampon = '';
        if (/^[0-9]{1,9}$/.test(numero)) envoyerNumero(numero);
        e.preventDefault();
        return;
    }
    if (e.key.length === 1) tampon += e.key;
});

// Plein écran au premier clic (navigateur en mode kiosque recommandé)
el('plein-ecran')?.addEventListener('click', () => {
    document.documentElement.requestFullscreen?.();
    el('plein-ecran').classList.add('hidden');
});

horloge();
setInterval(horloge, 10000);
interroger();
