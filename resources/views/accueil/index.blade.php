<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accueil · {{ config('salle.nom') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap">
    <style>
        :root { --fond: #0B0C10; --texte: #F3F4F6; --muet: #9CA3AF; --ok: #15803D; --ok-fonce: #166534; --ko: #DC2626; --ko-fonce: #991B1B; --vif: #E50914; --blanc: #FFFFFF; --ligne: #2A2D3A;
                --d: "Sora", "Segoe UI", sans-serif; --b: "DM Sans", "Segoe UI", sans-serif; color-scheme: dark; }
        * { box-sizing: border-box }
        html, body { height: 100% }
        body { margin: 0; background: var(--fond); color: var(--texte); font-family: var(--b); display: flex; flex-direction: column; padding: 28px clamp(16px, 4vw, 48px); gap: 22px }
        header, footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap }
        header b { font-family: var(--d); font-size: 32px; letter-spacing: 1px; text-transform: uppercase; color: #fff }
        #horloge { font-family: var(--d); font-size: 44px; font-weight: 600; font-variant-numeric: tabular-nums; color: #E50914 }
        footer { color: var(--muet); font-size: 16px }
        main { flex: 1; display: flex; min-height: 0 }
        .ecran { flex: 1; border-radius: 28px; display: flex; align-items: center; gap: clamp(20px, 4vw, 56px); padding: clamp(24px, 4vw, 56px) clamp(20px, 5vw, 72px); flex-wrap: wrap }
        .ecran[hidden] { display: none }
        .ok { background: var(--ok) } .ko { background: var(--ko) }
        .attente { border: 2px dashed var(--ligne); flex-direction: column; justify-content: center; text-align: center; background: rgb(20 22 30 / .4) }
        .visage { width: clamp(140px, 20vw, 230px); aspect-ratio: 1; border-radius: 50%; border: 6px solid var(--blanc); display: grid; place-items: center; flex-shrink: 0; overflow: hidden; font-family: var(--d); font-weight: 700; font-size: clamp(56px, 8vw, 96px) }
        .ok .visage { background: var(--ok-fonce) } .ko .visage { background: var(--ko-fonce) }
        .visage img { width: 100%; height: 100%; object-fit: cover }
        .msg { display: flex; flex-direction: column; gap: 12px; flex: 1 1 320px; min-width: 0 }
        .titre { font-family: var(--d); font-weight: 700; font-size: clamp(48px, 7.5vw, 96px); line-height: .95 }
        .qui { font-size: clamp(20px, 2.6vw, 30px); font-weight: 600 }
        .reste { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: 14px; font-size: clamp(17px, 2vw, 22px) }
        .boite { background: var(--blanc); color: var(--ok-fonce); border-radius: 16px; padding: 12px 22px; display: flex; align-items: baseline; gap: 10px; font-weight: 700 }
        .boite b { font-family: var(--d); font-size: 60px; line-height: 1 }
        .avis { background: var(--blanc); color: var(--ko-fonce); border-radius: 16px; padding: 14px 22px; font-weight: 700; font-size: clamp(17px, 2vw, 24px); margin-top: 12px }
        .attente .titre { font-size: clamp(38px, 5.5vw, 66px) }
        .attente p { color: var(--muet); font-size: clamp(16px, 1.8vw, 21px); margin: 0 }
        .file { color: #FFD48A; font-weight: 700 }
        #badge { position: absolute; left: -9999px; opacity: 0 }
        @media (prefers-reduced-motion: no-preference) { .ecran:not(.attente) { animation: entree .25s ease-out } @keyframes entree { from { transform: scale(.98); opacity: .6 } } }
    </style>
</head>
<body>
<header><b>{{ config('salle.nom') }}</b><span id="horloge"></span></header>

<main>
    <section class="ecran attente" id="attente">
        <svg width="140" height="140" viewBox="0 0 24 24" fill="none" stroke="#E50914" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M12 3c-3 0-5.5 2.5-5.5 5.5v3M17.5 8.5v4c0 3.5-2 6.5-5.5 8.5M9 12v1.5c0 2-1 3.5-2.5 4.5M12 8.5v5c0 2.5-1 4.5-3 6M15 10v3c0 2.2-.7 4-2 5.5"/></svg>
        <div class="titre">Posez votre doigt ou passez votre carte</div>
        <p>Pas d'abonnement ? Présentez-vous à la caisse · entrée journalière {{ number_format((int) config('salle.tarif_journalier'), 0, ',', ' ') }} FCFA</p>
        <p class="file" id="file" hidden></p>
    </section>

    <section class="ecran" id="fiche" role="status" aria-live="assertive" hidden>
        <div class="visage" id="visage"></div>
        <div class="msg">
            <div class="titre" id="titre"></div>
            <div class="qui" id="qui"></div>
            <div id="detail"></div>
        </div>
    </section>
</main>

<footer><span>{{ config('salle.adresse') }}</span><span id="etat">Connecté</span></footer>

<label for="badge" style="position:absolute;left:-9999px">Badge</label>
<input id="badge" autocomplete="off" aria-hidden="true" tabindex="-1">

<script>
(() => {
    const URL_DERNIER = @json(route('accueil.dernier'));
    const URL_BADGE = @json(route('accueil.badge'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const DUREE_MS = 8000;
    const el = (id) => document.getElementById(id);
    let dernierId = null, minuteur = null;

    const horloge = () => { el('horloge').textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }); };
    horloge(); setInterval(horloge, 10000);

    function afficher(p) {
        const fiche = el('fiche');
        fiche.className = 'ecran ' + (p.autorise ? 'ok' : 'ko');
        el('attente').hidden = true; fiche.hidden = false;

        const visage = el('visage'); visage.replaceChildren();
        if (p.client?.photo_url) { const img = new Image(); img.src = p.client.photo_url; img.alt = ''; visage.append(img); }
        else visage.textContent = p.autorise ? (p.client?.initiales || '✓') : '✕';

        el('titre').textContent = p.sortie ? (p.client ? `À bientôt, ${p.client.prenom} !` : 'À bientôt !')
            : p.autorise ? (p.client ? `Bienvenue, ${p.client.prenom} !` : 'Bienvenue !') : 'Accès refusé';
        el('qui').textContent = p.client ? [p.client.nom, p.formule].filter(Boolean).join(' · ') : (p.autorise ? '' : p.message);

        const d = el('detail'); d.replaceChildren();
        if (p.sortie) {
            d.className = 'reste'; d.textContent = 'Sortie enregistrée. Bonne journée !';
        } else if (p.autorise && p.jours_restants !== null && p.jours_restants !== undefined) {
            d.className = 'reste';
            const b = document.createElement('div'); b.className = 'boite';
            const n = document.createElement('b'); n.textContent = p.jours_restants; b.append(n, p.jours_restants > 1 ? ' jours restants' : ' jour restant');
            d.append(b, `Valide jusqu'au ${p.fin_droits}`);
        } else if (p.autorise && p.entrees_restantes !== null && p.entrees_restantes !== undefined) {
            d.className = 'reste';
            const b = document.createElement('div'); b.className = 'boite';
            const n = document.createElement('b'); n.textContent = p.entrees_restantes; b.append(n, ' entrée(s) restante(s)');
            d.append(b);
        } else if (p.autorise && p.methode === 'caisse') {
            d.className = 'reste'; d.textContent = 'Entrée journalière validée à la caisse';
        } else if (!p.autorise) {
            d.className = 'avis'; d.textContent = p.client ? `${p.message}. Merci de passer à la caisse.` : 'Merci de vous présenter à la caisse.';
        }

        clearTimeout(minuteur);
        minuteur = setTimeout(() => { fiche.hidden = true; el('attente').hidden = false; }, DUREE_MS);
    }

    async function interroger() {
        try {
            const r = await fetch(URL_DERNIER, { headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const p = await r.json();
            el('etat').textContent = 'Connecté';
            if (p && p.id !== dernierId) {
                if (dernierId !== null && p.il_y_a_secondes < 30) afficher(p);
                dernierId = p.id;
            }
        } catch (e) {
            el('etat').textContent = 'Connexion perdue, nouvel essai…';
        }
    }
    interroger(); setInterval(interroger, 2000);

    /* Lecteur USB de badge ou de QR code : il « tape » le code puis Entrée */
    const lireFile = () => { try { return JSON.parse(localStorage.getItem('gf-badges') || '[]'); } catch { return []; } };
    const ecrireFile = (f) => { try { localStorage.setItem('gf-badges', JSON.stringify(f)); } catch {} majFile(); };
    const majFile = () => { const n = lireFile().length; el('file').hidden = !n; el('file').textContent = `${n} pointage(s) par carte en attente d'envoi : la connexion est coupée.`; };

    async function envoyer(code) {
        const r = await fetch(URL_BADGE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ carte: code }),
        });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
    }

    const champ = el('badge');
    const garderFocus = () => { if (document.activeElement !== champ) champ.focus({ preventScroll: true }); };
    setInterval(garderFocus, 1000); document.addEventListener('click', garderFocus); garderFocus();

    champ.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        const code = champ.value.trim(); champ.value = '';
        if (code.length < 3) return;
        try {
            const p = await envoyer(code);
            dernierId = p.id; afficher(p);
        } catch {
            ecrireFile([...lireFile(), code]);
        }
    });

    async function viderFile() {
        const file = lireFile(); if (!file.length) return;
        const restants = [];
        for (const code of file) { try { await envoyer(code); } catch { restants.push(code); } }
        ecrireFile(restants);
    }
    addEventListener('online', viderFile); setInterval(viderFile, 15000); majFile();
})();
</script>
</body>
</html>
