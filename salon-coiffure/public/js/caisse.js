// Caisse : le ticket vit dans le navigateur, le serveur recalcule les prix à l'encaissement.
(function () {
    const { services, url, csrf } = window.CAISSE;
    const parId = new Map(services.map((s) => [s.id, s]));
    const panier = new Map(); // id du service -> quantité
    let categorie = '';
    let envoiEnCours = false;

    const el = (id) => document.getElementById(id);
    const grille = el('grille');
    const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' FCFA';
    const echapper = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const sansAccents = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const initiales = (nom) => nom.split(/[\s'+&-]+/).filter((m) => m.length > 1 || /\d/.test(m)).slice(0, 2).map((m) => m[0]).join('').toUpperCase();
    const teinte = (hex, alpha) => {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    // ---------- Cartes de services ----------
    grille.innerHTML = services.map((s) => `
        <button type="button" class="service" data-id="${s.id}" data-categorie="${s.categorie_id}"
                data-recherche="${echapper(sansAccents(s.nom + ' ' + s.code + ' ' + s.categorie))}">
            <div class="visuel" style="background:${teinte(s.couleur, .14)};color:${s.couleur}">
                <span class="categorie-badge" style="color:${s.couleur}">${echapper(s.categorie)}</span>
                <span class="initiales">${echapper(initiales(s.nom))}</span>
                <span class="qte"></span>
            </div>
            <div class="infos">
                <span class="code">${echapper(s.code)}</span>
                <span class="nom">${echapper(s.nom)}</span>
                <span class="prix">${fcfa(s.prix)}</span>
            </div>
        </button>`).join('');

    grille.addEventListener('click', (e) => {
        const carte = e.target.closest('.service');
        if (!carte) return;
        ajouter(Number(carte.dataset.id));
        carte.classList.remove('ajout');
        void carte.offsetWidth; // relance l'animation
        carte.classList.add('ajout');
    });

    function filtrer() {
        const texte = sansAccents(el('recherche').value.trim());
        let visibles = 0;
        grille.querySelectorAll('.service').forEach((carte) => {
            const ok = (!categorie || carte.dataset.categorie === categorie)
                && (!texte || carte.dataset.recherche.includes(texte));
            carte.hidden = !ok;
            if (ok) visibles++;
        });
        el('aucun').hidden = visibles > 0 || services.length === 0;
    }

    el('recherche').addEventListener('input', filtrer);
    el('pastilles').addEventListener('click', (e) => {
        const bouton = e.target.closest('button');
        if (!bouton) return;
        el('pastilles').querySelectorAll('button').forEach((b) => b.classList.toggle('actif', b === bouton));
        categorie = bouton.dataset.categorie;
        filtrer();
    });

    // ---------- Ticket ----------
    function ajouter(id, delta = 1) {
        const quantite = (panier.get(id) || 0) + delta;
        if (quantite <= 0) panier.delete(id); else panier.set(id, Math.min(quantite, 99));
        el('message').textContent = '';
        afficher();
    }

    function total() {
        let t = 0;
        panier.forEach((q, id) => { t += parId.get(id).prix * q; });
        return t;
    }

    function modePaiement() {
        return document.querySelector('input[name=mode]:checked').value;
    }

    function afficher() {
        const lignes = el('lignes');
        lignes.querySelectorAll('.tligne').forEach((l) => l.remove());
        el('ticket-vide').hidden = panier.size > 0;
        el('vider').hidden = panier.size === 0;

        panier.forEach((q, id) => {
            const s = parId.get(id);
            const ligne = document.createElement('div');
            ligne.className = 'tligne';
            ligne.innerHTML = `
                <div class="desc"><b>${echapper(s.nom)}</b><small>${fcfa(s.prix)}</small></div>
                <div class="quantite">
                    <button type="button" data-action="moins" aria-label="Retirer un">−</button>
                    <span>${q}</span>
                    <button type="button" data-action="plus" aria-label="Ajouter un">+</button>
                </div>
                <div class="montant">${fcfa(s.prix * q)}</div>
                <button type="button" class="suppr" data-action="suppr" aria-label="Supprimer la ligne">✕</button>`;
            ligne.dataset.id = id;
            lignes.appendChild(ligne);
        });

        grille.querySelectorAll('.service').forEach((carte) => {
            const q = panier.get(Number(carte.dataset.id)) || 0;
            carte.classList.toggle('dans-ticket', q > 0);
            carte.querySelector('.qte').textContent = q ? '×' + q : '';
        });

        el('total').textContent = fcfa(total());
        afficherMonnaie();
    }

    el('lignes').addEventListener('click', (e) => {
        const bouton = e.target.closest('button[data-action]');
        if (!bouton) return;
        const id = Number(bouton.closest('.tligne').dataset.id);
        const action = bouton.dataset.action;
        if (action === 'plus') ajouter(id, 1);
        if (action === 'moins') ajouter(id, -1);
        if (action === 'suppr') { panier.delete(id); afficher(); }
    });

    el('vider').addEventListener('click', () => {
        if (confirm('Vider le ticket ?')) { panier.clear(); vider(); }
    });

    // ---------- Paiement ----------
    function afficherMonnaie() {
        const especes = modePaiement() === 'especes';
        el('bloc-especes').hidden = !especes;
        const recu = el('recu').value === '' ? null : Number(el('recu').value);
        const monnaie = el('monnaie');
        const rendu = recu === null ? 0 : recu - total();
        monnaie.textContent = fcfa(Math.max(0, rendu));
        monnaie.classList.toggle('insuffisant', recu !== null && rendu < 0);
        if (recu !== null && rendu < 0) monnaie.textContent = 'Manque ' + fcfa(-rendu);

        el('encaisser').disabled = envoiEnCours || panier.size === 0 || (especes && recu !== null && rendu < 0);
    }

    document.querySelectorAll('input[name=mode]').forEach((r) => r.addEventListener('change', afficherMonnaie));
    el('recu').addEventListener('input', afficherMonnaie);
    el('billets').addEventListener('click', (e) => {
        const bouton = e.target.closest('button');
        if (!bouton) return;
        el('recu').value = bouton.dataset.billet === 'exact' ? total() : bouton.dataset.billet;
        afficherMonnaie();
    });

    function vider() {
        el('recu').value = '';
        document.querySelector('input[name=mode]').checked = true;
        afficher();
    }

    async function encaisser() {
        if (el('encaisser').disabled) return;
        envoiEnCours = true;
        afficherMonnaie();
        const message = el('message');
        message.className = 'ticket-message';
        message.textContent = 'Enregistrement…';

        const especes = modePaiement() === 'especes';
        try {
            const reponse = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    lignes: [...panier].map(([service_id, quantite]) => ({ service_id, quantite })),
                    mode_paiement: modePaiement(),
                    montant_recu: especes && el('recu').value !== '' ? Number(el('recu').value) : null,
                }),
            });
            const donnees = await reponse.json().catch(() => ({}));

            if (reponse.status === 419 || reponse.status === 401) {
                throw new Error('Session expirée : rechargez la page (F5) puis recommencez.');
            }
            if (!reponse.ok) {
                const erreurs = donnees.errors ? Object.values(donnees.errors).flat() : [];
                throw new Error(erreurs[0] || donnees.message || 'Erreur lors de l\'enregistrement.');
            }

            // Impression dans un cadre caché : la caisse reste ouverte, prête pour le client suivant
            el('impression').src = donnees.ticket_url + '&cadre=1';
            panier.clear();
            vider();
            message.className = 'ticket-message';
            message.innerHTML = `✅ Ticket <b>${echapper(donnees.numero)}</b> enregistré. `
                + `<a href="${echapper(donnees.ticket_url.replace('imprimer=1', 'imprimer=0'))}">Voir / réimprimer</a>`;
        } catch (erreur) {
            message.className = 'ticket-message erreur-msg';
            message.textContent = erreur.message;
        } finally {
            envoiEnCours = false;
            afficherMonnaie();
        }
    }

    el('encaisser').addEventListener('click', encaisser);

    // Raccourcis clavier : / pour rechercher, Entrée dans la recherche = ajoute le premier résultat
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== el('recherche') && document.activeElement !== el('recu')) {
            e.preventDefault();
            el('recherche').focus();
        }
    });
    el('recherche').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const premier = grille.querySelector('.service:not([hidden])');
        if (premier) premier.click();
        el('recherche').select();
    });

    afficher();
})();
