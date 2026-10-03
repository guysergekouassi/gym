// Scripts de l'interface. Aucun script inline dans les vues (CSP stricte) :
// tout est branché ici via des attributs data-*.

const $$ = (selecteur, racine = document) => Array.from(racine.querySelectorAll(selecteur));

// --- Menu latéral (mobile) ---
const sidebar = document.querySelector('[data-sidebar]');
const fond = document.querySelector('[data-sidebar-backdrop]');
function basculerMenu(ouvrir) {
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full', !ouvrir);
    fond?.classList.toggle('hidden', !ouvrir);
}
document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => basculerMenu(true));
fond?.addEventListener('click', () => basculerMenu(false));

// --- Anti double-clic : un formulaire envoyé ne peut pas l'être une seconde fois ---
$$('form').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (form.dataset.envoye === '1') {
            e.preventDefault();
            return;
        }
        if (form.method.toLowerCase() === 'post') {
            form.dataset.envoye = '1';
            $$('button[type="submit"], button:not([type])', form).forEach((b) => {
                b.disabled = true;
                b.classList.add('opacity-70');
            });
        }
    });
});

// --- Confirmation avant action sensible ---
$$('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    }, { capture: true });
});

// --- Fenêtres modales (<dialog>) ---
$$('[data-dialog-open]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
        const dialog = document.getElementById(bouton.dataset.dialogOpen);
        if (!dialog) return;
        if (bouton.dataset.action) dialog.querySelector('form')?.setAttribute('action', bouton.dataset.action);
        if (bouton.dataset.label) {
            const cible = dialog.querySelector('[data-dialog-label]');
            if (cible) cible.textContent = bouton.dataset.label;
        }
        dialog.showModal();
    });
});
$$('[data-dialog-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog')?.close()));

// --- Onglets (caisse) : l'état visuel suit aria-selected ---
$$('[data-tabs]').forEach((groupe) => {
    const boutons = $$('[data-tab]', groupe);
    const panneaux = $$('[data-panel]', groupe);
    const activer = (nom) => {
        boutons.forEach((b) => b.setAttribute('aria-selected', b.dataset.tab === nom ? 'true' : 'false'));
        panneaux.forEach((p) => { p.hidden = p.dataset.panel !== nom; });
    };
    boutons.forEach((b) => b.addEventListener('click', () => activer(b.dataset.tab)));
    $$('[data-tab-aller]', groupe).forEach((b) => b.addEventListener('click', () => activer(b.dataset.tabAller)));
    activer(groupe.dataset.tabs || boutons[0]?.dataset.tab);
});

// --- Caisse : quantité de tickets et total ---
const fcfa = (n) => `${new Intl.NumberFormat('fr-FR').format(n).replace(/\u202f|\u00a0/g, ' ')} FCFA`;
$$('[data-calcul-passage]').forEach((form) => {
    const prix = Number(form.dataset.prix);
    const champ = form.querySelector('[data-quantite]');
    const maj = () => {
        let q = Math.round(Number(champ.value) || 1);
        q = Math.min(10, Math.max(1, q));
        champ.value = q;
        $$('[data-total], [data-total-court]', form).forEach((el) => { el.textContent = fcfa(prix * q); });
        $$('[data-recap-quantite]', form).forEach((el) => { el.textContent = q; });
    };
    form.querySelector('[data-quantite-moins]')?.addEventListener('click', () => { champ.value = Number(champ.value) - 1; maj(); });
    form.querySelector('[data-quantite-plus]')?.addEventListener('click', () => { champ.value = Number(champ.value) + 1; maj(); });
    champ.addEventListener('change', maj);
    maj();
});

// --- Recherche de client (caisse) ---
$$('[data-recherche-client]').forEach((bloc) => {
    const url = bloc.dataset.url;
    const champ = bloc.querySelector('[data-client-q]');
    const idCache = bloc.querySelector('[data-client-id]');
    const liste = bloc.querySelector('[data-client-resultats]');
    const choisi = bloc.querySelector('[data-client-choisi]');
    const choisiNom = bloc.querySelector('[data-client-choisi-nom]');
    const effacer = bloc.querySelector('[data-client-effacer]');
    let minuteur = null;
    let requete = null;

    const selectionner = (c) => {
        idCache.value = c ? c.id : '';
        if (choisiNom) choisiNom.textContent = c ? `${c.nom}${c.fin_droits ? ' · droits jusqu’au ' + c.fin_droits : ''}` : '';
        choisi?.classList.toggle('hidden', !c);
        champ.closest('[data-client-champ]')?.classList.toggle('hidden', !!c);
        liste.classList.add('hidden');
        champ.value = '';
    };

    effacer?.addEventListener('click', () => { selectionner(null); champ.focus(); });

    champ.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') e.preventDefault(); // Entrée ne doit pas encaisser par accident
    });

    champ.addEventListener('input', () => {
        clearTimeout(minuteur);
        const q = champ.value.trim();
        if (q.length < 2) { liste.classList.add('hidden'); return; }

        minuteur = setTimeout(async () => {
            requete?.abort();
            requete = new AbortController();
            try {
                const reponse = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: requete.signal,
                    credentials: 'same-origin',
                });
                if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
                const clients = await reponse.json();

                liste.replaceChildren();
                if (clients.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'px-4 py-3 text-sm text-slate-400';
                    li.textContent = 'Aucun client trouvé';
                    liste.appendChild(li);
                }
                clients.forEach((c) => {
                    const li = document.createElement('li');
                    const bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50';
                    const gauche = document.createElement('span');
                    const nom = document.createElement('span');
                    nom.className = 'block font-semibold text-slate-900';
                    nom.textContent = c.nom;
                    const details = document.createElement('span');
                    details.className = 'block text-xs text-slate-500';
                    details.textContent = [c.type, c.telephone].filter(Boolean).join(' · ');
                    gauche.append(nom, details);
                    const droite = document.createElement('span');
                    droite.className = c.fin_droits ? 'pill-green' : 'pill-gray';
                    droite.textContent = c.fin_droits ? `→ ${c.fin_droits}` : 'Sans abonnement';
                    bouton.append(gauche, droite);
                    bouton.addEventListener('click', () => selectionner(c));
                    li.appendChild(bouton);
                    liste.appendChild(li);
                });
                liste.classList.remove('hidden');
            } catch (erreur) {
                if (erreur.name !== 'AbortError') {
                    console.error('Recherche client impossible :', erreur);
                    liste.classList.add('hidden');
                }
            }
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!bloc.contains(e.target)) liste.classList.add('hidden');
    });
});

// --- Récapitulatif dynamique de la formule choisie (caisse) ---
$$('[data-recap-source]').forEach((form) => {
    const maj = () => {
        const choisie = form.querySelector('input[name="formule_id"]:checked');
        if (!choisie) return;
        $$('[data-recap-montant]', form).forEach((el) => { el.textContent = choisie.dataset.prix; });
        $$('[data-recap-nom]', form).forEach((el) => { el.textContent = choisie.dataset.nom; });
    };
    form.addEventListener('change', maj);
    maj();
});

// --- Reçu : impression automatique puis retour à la caisse ---
const recu = document.querySelector('[data-recu-auto]');
if (recu) {
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    window.addEventListener('afterprint', () => { window.location.href = recu.dataset.retour; });
}
$$('[data-imprimer]').forEach((b) => b.addEventListener('click', () => window.print()));

// --- Copier dans le presse-papiers ---
$$('[data-copier]').forEach((b) => {
    b.addEventListener('click', async () => {
        const source = document.getElementById(b.dataset.copier);
        try {
            await navigator.clipboard.writeText(source.textContent.trim());
            b.textContent = 'Copié ✓';
        } catch {
            window.getSelection().selectAllChildren(source);
        }
    });
});

// --- Champs remplis par le lecteur de badge USB : son "Entrée" final ne doit pas valider le formulaire ---
$$('[data-no-enter]').forEach((champ) => {
    champ.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') e.preventDefault();
    });
});

// --- Afficher / masquer le mot de passe ---
$$('[data-afficher-mdp]').forEach((bouton) => {
    const champ = document.getElementById(bouton.dataset.afficherMdp);
    bouton.addEventListener('click', () => {
        const visible = champ.type === 'password';
        champ.type = visible ? 'text' : 'password';
        bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        bouton.querySelector('[data-oeil-ferme]')?.classList.toggle('hidden', visible);
        bouton.querySelector('[data-oeil-ouvert]')?.classList.toggle('hidden', !visible);
        champ.focus();
    });
});

// --- Bouton qui remplit un champ avec une valeur proposée (ex. n° de pointeuse libre) ---
$$('[data-remplir]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
        const champ = document.getElementById(bouton.dataset.remplir);
        if (champ) champ.value = bouton.dataset.valeur;
        bouton.remove();
    });
});

// --- Fenêtre ouverte d'office (ex. erreurs de saisie dans « Nouveau client ») ---
$$('dialog[data-ouvrir]').forEach((d) => d.showModal());
$$('[data-remplir-garder]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
        const champ = document.getElementById(bouton.dataset.remplirGarder);
        if (champ) champ.value = bouton.dataset.valeur;
    });
});

// --- Paramètres : aperçu du ticket en direct ---
$$('[data-apercu]').forEach((champ) => {
    champ.addEventListener('input', () => {
        $$(`[data-apercu-cible="${champ.dataset.apercu}"]`).forEach((el) => { el.textContent = champ.value; });
    });
});

// --- Barre latérale : sections repliables (Quotidien / Pilotage / Administration) ---
const memoire = {
    lire: (cle) => { try { return localStorage.getItem(cle); } catch { return null; } },
    ecrire: (cle, val) => { try { localStorage.setItem(cle, val); } catch { /* stockage indisponible */ } },
};
$$('[data-menu-section]').forEach((section) => {
    const bouton = section.querySelector('[data-menu-bascule]');
    const liens = section.querySelector('[data-menu-liens]');
    const cle = `menu.${section.dataset.menuSection}`;
    const appliquer = (ouvert) => {
        liens.hidden = !ouvert;
        bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    };
    // La section de la page en cours reste toujours ouverte
    const contientPageActive = !!liens.querySelector('.nav-link.active');
    appliquer(contientPageActive || memoire.lire(cle) !== 'ferme');
    bouton.addEventListener('click', () => {
        const ouvrir = liens.hidden;
        appliquer(ouvrir);
        memoire.ecrire(cle, ouvrir ? 'ouvert' : 'ferme');
    });
});
