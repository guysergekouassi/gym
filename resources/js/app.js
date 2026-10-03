// Scripts de l'interface. Aucun script inline dans les vues (CSP stricte) :
// tout est branché ici via des attributs data-*.

const $$ = (selecteur, racine = document) => Array.from(racine.querySelectorAll(selecteur));

// --- Bouton ≡ : ouvre / ferme la barre latérale ---
// Petit écran : la barre glisse par-dessus la page. Grand écran : elle se range et la page prend toute la largeur.
const app = document.querySelector('[data-app]');
const sidebar = document.querySelector('[data-sidebar]');
const fond = document.querySelector('[data-sidebar-backdrop]');
const grandEcran = () => window.matchMedia('(min-width: 1024px)').matches;
function basculerMobile(ouvrir) {
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full', !ouvrir);
    fond?.classList.toggle('hidden', !ouvrir);
}
document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (grandEcran() && app) {
        const fermer = app.dataset.menu !== 'ferme';
        app.dataset.menu = fermer ? 'ferme' : 'ouvert';
        document.cookie = `menu=${fermer ? 'ferme' : 'ouvert'}; path=/; max-age=31536000; SameSite=Lax`;
    } else {
        basculerMobile(sidebar.classList.contains('-translate-x-full'));
    }
});
fond?.addEventListener('click', () => basculerMobile(false));

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

// --- Choix du client (caisse) : liste déroulante avec recherche en tête ---
$$('[data-recherche-client]').forEach((bloc) => {
    const url = bloc.dataset.url;
    const requis = bloc.dataset.requis === '1';
    const ouvrir = bloc.querySelector('[data-client-ouvrir]');
    const libelle = bloc.querySelector('[data-client-libelle]');
    const panneau = bloc.querySelector('[data-client-panneau]');
    const champ = bloc.querySelector('[data-client-q]');
    const idCache = bloc.querySelector('[data-client-id]');
    const liste = bloc.querySelector('[data-client-resultats]');
    const vider = bloc.querySelector('[data-client-vider]');
    let minuteur = null;
    let requete = null;

    const fermer = () => { panneau.hidden = true; ouvrir.setAttribute('aria-expanded', 'false'); };
    const selectionner = (c) => {
        idCache.value = c ? c.id : '';
        libelle.textContent = c ? `${c.nom}${c.fin_droits ? ' · droits jusqu’au ' + c.fin_droits : ''}` : libelle.dataset.vide;
        libelle.classList.toggle('text-slate-400', !c);
        libelle.classList.toggle('font-semibold', !!c);
        libelle.classList.toggle('text-slate-900', !!c);
        vider?.classList.toggle('hidden', !c);
        fermer();
        ouvrir.focus();
    };

    const ligne = (contenu, action, classes = '') => {
        const li = document.createElement('li');
        const bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = `flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-brand-50 focus:bg-brand-50 focus:outline-none ${classes}`;
        bouton.append(...contenu);
        bouton.addEventListener('click', action);
        li.appendChild(bouton);
        return li;
    };

    const charger = async () => {
        requete?.abort();
        requete = new AbortController();
        try {
            const reponse = await fetch(`${url}?q=${encodeURIComponent(champ.value.trim())}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: requete.signal,
                credentials: 'same-origin',
            });
            if (!reponse.ok) throw new Error(`HTTP ${reponse.status}`);
            const clients = await reponse.json();

            liste.replaceChildren();
            if (!requis) {
                const texte = document.createElement('span');
                texte.className = 'italic text-slate-500';
                texte.textContent = 'Aucun client (passage anonyme)';
                liste.appendChild(ligne([texte], () => selectionner(null)));
            }
            if (clients.length === 0) {
                const li = document.createElement('li');
                li.className = 'px-4 py-3 text-sm text-slate-400';
                li.textContent = 'Aucun client trouvé';
                liste.appendChild(li);
            }
            clients.forEach((c) => {
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
                liste.appendChild(ligne([gauche, droite], () => selectionner(c), String(c.id) === idCache.value ? 'bg-brand-50' : ''));
            });
        } catch (erreur) {
            if (erreur.name !== 'AbortError') console.error('Liste des clients indisponible :', erreur);
        }
    };

    vider?.addEventListener('click', () => selectionner(null));
    ouvrir.addEventListener('click', () => {
        if (!panneau.hidden) { fermer(); return; }
        panneau.hidden = false;
        ouvrir.setAttribute('aria-expanded', 'true');
        champ.value = '';
        charger();
        champ.focus();
    });
    champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(charger, 200); });
    champ.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { // Entrée choisit le premier client de la liste, sans valider le formulaire
            e.preventDefault();
            liste.querySelectorAll('button')[requis ? 0 : 1]?.click();
        }
        if (e.key === 'Escape') fermer();
        if (e.key === 'ArrowDown') { e.preventDefault(); liste.querySelector('button')?.focus(); }
    });
    liste.addEventListener('keydown', (e) => {
        const boutons = [...liste.querySelectorAll('button')];
        const i = boutons.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); boutons[Math.min(i + 1, boutons.length - 1)]?.focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); (i <= 0 ? champ : boutons[i - 1]).focus(); }
        if (e.key === 'Escape') fermer();
    });
    document.addEventListener('click', (e) => { if (!bloc.contains(e.target)) fermer(); });
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

// --- Filtre de période : Calendrier (exercice/mois/semaine/jour) ou Période (du/au) ---
$$('[data-filtre-periode]').forEach((form) => {
    const champMode = form.querySelector('[data-mode-champ]');
    const basculer = (mode) => {
        champMode.value = mode;
        $$('[data-mode-bouton]', form).forEach((b) => {
            const actif = b.dataset.modeBouton === mode;
            b.className = b.className.replace(actif ? b.dataset.classeInactif : b.dataset.classeActif, actif ? b.dataset.classeActif : b.dataset.classeInactif);
        });
        $$('[data-mode-bloc]', form).forEach((bloc) => {
            const visible = bloc.dataset.modeBloc === mode;
            bloc.hidden = !visible;
            // Les champs du mode caché ne sont pas envoyés
            $$('select, input', bloc).forEach((c) => { c.disabled = !visible; });
        });
        if (mode === 'calendrier') {
            const mois = form.querySelector('[name="mois"]');
            ['semaine', 'jour'].forEach((n) => { form.querySelector(`[name="${n}"]`).disabled = !mois.value; });
        }
    };
    $$('[data-mode-bouton]', form).forEach((b) => b.addEventListener('click', () => {
        basculer(b.dataset.modeBouton);
        if (b.dataset.modeBouton === 'calendrier') form.submit();
    }));

    // Un choix dans le calendrier recharge tout de suite, en vidant les choix qui en dépendent
    $$('select[data-auto]', form).forEach((select) => {
        select.addEventListener('change', () => {
            (select.dataset.reinitialiser || '').split(',').filter(Boolean).forEach((nom) => {
                const dependant = form.querySelector(`[name="${nom}"]`);
                if (dependant) dependant.value = '';
            });
            form.submit();
        });
    });
});

// --- Photo du client : aperçu, nom du fichier et confirmation dès le choix ---
$$('[data-champ-photo]').forEach((bloc) => {
    const champ = bloc.querySelector('input[type="file"]');
    const apercu = bloc.querySelector('[data-photo-apercu]');
    const titre = bloc.querySelector('[data-photo-titre]');
    const detail = bloc.querySelector('[data-photo-detail]');
    const ok = bloc.querySelector('[data-photo-ok]');
    const zone = bloc.querySelector('[data-photo-zone]');
    const retirer = bloc.querySelector('[data-photo-retirer]');
    const initial = { src: apercu.getAttribute('src'), titre: titre.textContent, detail: detail.textContent };
    let url = null;

    const reinitialiser = () => {
        if (url) URL.revokeObjectURL(url);
        url = null;
        champ.value = '';
        if (initial.src) apercu.src = initial.src; else { apercu.removeAttribute('src'); apercu.classList.add('hidden'); }
        titre.textContent = initial.titre;
        detail.textContent = initial.detail;
        ok.classList.add('hidden');
        retirer.classList.add('hidden');
        zone.classList.remove('border-brand-400', 'bg-brand-50/60', 'border-red-300', 'bg-red-50');
    };

    champ.addEventListener('change', () => {
        const fichier = champ.files[0];
        if (!fichier) { reinitialiser(); return; }
        const valide = ['image/jpeg', 'image/png', 'image/webp'].includes(fichier.type) && fichier.size <= 2 * 1024 * 1024;
        if (!valide) {
            reinitialiser();
            titre.textContent = 'Fichier refusé';
            detail.textContent = 'Choisissez une image JPG, PNG ou WebP de 2 Mo maximum.';
            zone.classList.add('border-red-300', 'bg-red-50');
            return;
        }
        if (url) URL.revokeObjectURL(url);
        url = URL.createObjectURL(fichier);
        apercu.src = url;
        apercu.classList.remove('hidden');
        titre.textContent = fichier.name;
        detail.textContent = `${Math.max(1, Math.round(fichier.size / 1024))} Ko`;
        ok.classList.remove('hidden');
        retirer.classList.remove('hidden');
        zone.classList.add('border-brand-400', 'bg-brand-50/60');
    });
    retirer.addEventListener('click', reinitialiser);
});

// --- Menus déroulants (<details>) : se ferment quand on clique ailleurs ---
document.addEventListener('click', (e) => {
    $$('details[open]').forEach((d) => {
        if (!d.contains(e.target)) d.removeAttribute('open');
    });
});
