{{--
    Fenêtres de formulaire : tout lien vers une page « …/create » ou « …/edit » s'ouvre dans une fenêtre.
    Le formulaire de la page (form.form-card) y est chargé tel quel, puis envoyé sans recharger la page :
    - erreurs de saisie affichées sous chaque champ, la fenêtre reste ouverte ;
    - succès : la fenêtre se ferme et la page de destination affiche la notification.
    Un lien avec data-sans-modale ouvre la page normalement.
    Les scripts propres à un formulaire portent l'attribut data-modal-script pour être exécutés dans la fenêtre.
--}}
<script>
(() => {
    const SVG_PLUS = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
    const SVG_CRAYON = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>';
    const estFormulaire = (a) => {
        if (!a || a.target || a.hasAttribute('data-sans-modale') || a.origin !== location.origin) return false;
        return /\/(create|edit)$/.test(a.pathname);
    };

    let dialogue = null;

    function construire() {
        dialogue = document.createElement('dialog');
        dialogue.className = 'modale';
        dialogue.setAttribute('aria-labelledby', 'modale-titre');
        dialogue.innerHTML = `
            <button type="button" class="modale-fermer" aria-label="Fermer" data-fermer>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <header class="modale-entete"><span class="modale-icone"></span><h2 id="modale-titre"></h2></header>
            <div class="modale-corps"></div>
            <footer class="modale-pied"></footer>`;
        document.body.append(dialogue);
        dialogue.addEventListener('click', (e) => { if (e.target.closest('[data-fermer]') || e.target === dialogue) fermer(); });
        dialogue.addEventListener('cancel', (e) => { e.preventDefault(); fermer(); });
    }

    function fermer() {
        if (!dialogue?.open || dialogue.classList.contains('se-ferme')) return;
        dialogue.classList.add('se-ferme');
        setTimeout(() => { dialogue.close(); dialogue.classList.remove('se-ferme'); }, 180);
    }

    async function ouvrir(url, titreLien) {
        if (!dialogue) construire();
        document.querySelector('[data-recherche]')?.setAttribute('hidden', '');
        const creation = /\/create$/.test(new URL(url, location.href).pathname);
        dialogue.querySelector('.modale-icone').innerHTML = creation ? SVG_PLUS : SVG_CRAYON;
        dialogue.querySelector('#modale-titre').textContent = titreLien || 'Chargement…';
        dialogue.querySelector('.modale-corps').innerHTML = '<div class="modale-attente"><span class="modale-rond"></span>Chargement du formulaire…</div>';
        dialogue.querySelector('.modale-pied').innerHTML = '';
        if (!dialogue.open) dialogue.showModal();

        try {
            const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            const form = doc.querySelector('.content form.form-card');
            if (!form) { location.href = url; return; }

            dialogue.querySelector('#modale-titre').textContent = doc.querySelector('.content h1')?.textContent.trim() || titreLien || '';

            // Le bouton principal passe dans le pied de la fenêtre, « Annuler » la ferme
            form.id = form.id || 'modale-form';
            const actions = [...form.querySelectorAll(':scope > .actions')].pop();
            const principal = actions?.querySelector('button:not([type="button"])');
            actions?.remove();
            const pied = dialogue.querySelector('.modale-pied');
            const annuler = document.createElement('button');
            annuler.type = 'button'; annuler.className = 'btn ghost'; annuler.textContent = 'Annuler'; annuler.dataset.fermer = '';
            pied.append(annuler);
            if (principal) { principal.setAttribute('form', form.id); pied.append(principal); }

            const corps = dialogue.querySelector('.modale-corps');
            corps.replaceChildren(document.adoptNode(form));

            // Scripts propres au formulaire (ex. capture d'empreinte, champs selon le type de formule)
            doc.querySelectorAll('script[data-modal-script]').forEach((s) => {
                const n = document.createElement('script'); n.textContent = s.textContent; corps.append(n);
            });

            form.addEventListener('submit', envoyer);
            corps.querySelector('input:not([type="hidden"]):not([readonly]), select, textarea')?.focus();
        } catch (erreur) {
            console.error('Formulaire impossible à charger :', erreur);
            fermer();
            window.gfAlerte?.fire({ icon: 'error', title: 'Formulaire indisponible', text: 'Le formulaire n’a pas pu être chargé. Vérifiez la connexion puis réessayez.', confirmButtonText: 'Compris' });
        }
    }

    function effacerErreurs(form) {
        form.querySelectorAll('.erreur-champ, .modale-erreurs').forEach((e) => e.remove());
        form.querySelectorAll('.a-erreur').forEach((e) => e.classList.remove('a-erreur'));
    }

    function afficherErreurs(form, erreurs) {
        const generales = [];
        let premier = null;
        Object.entries(erreurs).forEach(([cle, messages]) => {
            const nom = cle.replace(/\.(\w+)/g, '[$1]');
            const champ = form.querySelector(`[name="${nom}"], [name="${nom}[]"]`);
            const bloc = champ?.closest('.fld, .check');
            if (!bloc) { generales.push(messages[0]); return; }
            bloc.classList.add('a-erreur');
            const m = document.createElement('span'); m.className = 'erreur-champ'; m.textContent = messages[0];
            bloc.append(m);
            premier ??= champ;
        });
        if (generales.length) {
            const b = document.createElement('div'); b.className = 'modale-erreurs'; b.setAttribute('role', 'alert');
            generales.forEach((t) => { const p = document.createElement('p'); p.textContent = t; b.append(p); });
            form.prepend(b);
        }
        (premier || form).scrollIntoView({ behavior: 'smooth', block: 'center' });
        premier?.focus({ preventScroll: true });
    }

    async function envoyer(e) {
        e.preventDefault();
        const form = e.target;
        const bouton = dialogue.querySelector(`.modale-pied [form="${form.id}"]`);
        effacerErreurs(form);
        bouton?.classList.add('en-cours'); bouton?.setAttribute('disabled', '');

        try {
            const r = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Modal': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (r.status === 422) { afficherErreurs(form, (await r.json()).errors || {}); return; }
            if (!r.ok) throw new Error(`HTTP ${r.status}`);

            const { redirect } = await r.json();
            fermer();
            const cible = new URL(redirect, location.href);
            if (cible.pathname === location.pathname && cible.search === location.search) location.reload();
            else location.href = cible.href;
        } catch (erreur) {
            console.error('Envoi impossible :', erreur);
            window.gfAlerte?.fire({ icon: 'error', title: 'Enregistrement impossible', text: 'Une erreur est survenue. Réessayez, ou rechargez la page si le problème continue.', confirmButtonText: 'Compris' });
        } finally {
            bouton?.classList.remove('en-cours'); bouton?.removeAttribute('disabled');
        }
    }

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 || !estFormulaire(a)) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        ouvrir(a.href, a.textContent.trim());
    }, true);

    window.gfModale = { ouvrir, fermer };
})();
</script>
