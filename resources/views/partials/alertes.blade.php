{{--
    Fenêtres SweetAlert2 aux couleurs de GymFlow.
    - Messages de session : succès en notification, erreurs en fenêtre (désactivable avec ['flash' => false]).
    - <form data-confirmer="Titre" data-texte="…" data-bouton="Archiver" data-variante="danger"> : confirmation avant l'envoi.
      data-texte-depuis="#selecteur" reprend le texte d'un élément ({texte} dans data-texte) ;
      data-confirmer-si="[name=client_id]" ne confirme que si ce champ est rempli.
    - <form data-motif="Titre" data-motif-champ="motif" data-motif-aide="…"> : demande un motif, placé dans le champ caché.
    - window.gfToast('Copié', 'success') pour une notification ponctuelle.
--}}
<script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
<style>
    @keyframes gf-entree { from { opacity: 0; transform: translateY(18px) scale(.94) } to { opacity: 1; transform: none } }
    @keyframes gf-sortie { to { opacity: 0; transform: translateY(8px) scale(.97) } }
    @keyframes gf-toast-entree { from { opacity: 0; transform: translateX(40px) scale(.96) } to { opacity: 1; transform: none } }
    @keyframes gf-toast-sortie { to { opacity: 0; transform: translateX(30px) } }
    @keyframes gf-pastille { 0% { transform: scale(.4); opacity: 0 } 60% { transform: scale(1.08); opacity: 1 } 100% { transform: scale(1) } }
    @keyframes gf-onde { from { box-shadow: 0 0 0 0 var(--onde) } to { box-shadow: 0 0 0 18px transparent } }

    .swal2-container { font-family: var(--f-body, "DM Sans", "Segoe UI", sans-serif); z-index: 2000 }
    .swal2-container.swal2-backdrop-show { background: rgb(26 32 51 / .28) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(6px) }
    .gf-entree { animation: gf-entree .38s cubic-bezier(.2, .8, .2, 1) both }
    .gf-sortie { animation: gf-sortie .18s ease-in both }

    .swal2-popup.gf-pop { border-radius: 26px; padding: 32px 28px 26px; width: min(440px, calc(100vw - 32px)); color: var(--fg, #F3F4F6); background: var(--surface, #14151D); box-shadow: 0 40px 100px -30px rgb(0 0 0 / .9), 0 0 0 1px rgb(255 255 255 / .08) }
    .gf-pop .swal2-title { font-family: var(--f-display, "DM Sans", sans-serif); font-size: 22px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; padding: 0; color: var(--fg, #F3F4F6) }
    .gf-pop .swal2-html-container { font-size: 14.5px; line-height: 1.6; color: var(--muted, #9CA3AF); margin: 10px 0 0; padding: 0 }
    .gf-pop .swal2-html-container ul { text-align: left; margin: 12px 0 0; padding: 12px 14px 12px 32px; color: var(--fg, #F3F4F6); background: var(--surface-2, #1B1D26); border-radius: 14px; border: 1px solid var(--line, rgb(255 255 255 / .08)) }
    .gf-pop .swal2-html-container li + li { margin-top: 6px }
    .gf-pop .swal2-actions { gap: 10px; margin-top: 24px; width: 100%; flex-wrap: nowrap }
    .gf-pop .swal2-actions button { flex: 1; min-height: 48px; border-radius: 14px; font: 700 14.5px var(--f-body, "DM Sans", sans-serif); border: 0; cursor: pointer; margin: 0; transition: transform .15s cubic-bezier(.2, .8, .2, 1), box-shadow .2s, filter .2s }
    .gf-pop .swal2-actions button:hover { transform: translateY(-1px); filter: brightness(1.05) }
    .gf-pop .swal2-actions button:active { transform: scale(.98) }
    .gf-ok { background: linear-gradient(135deg, #FF2E3B, #E50914); color: #fff; box-shadow: 0 12px 24px -12px rgb(229 9 20 / .8) }
    .gf-danger { background: linear-gradient(135deg, #EF4444, #B91C1C); color: #fff; box-shadow: 0 12px 24px -12px rgb(229 9 20 / .8) }
    .gf-annuler { background: var(--surface-2, #1B1D26); color: var(--fg, #F3F4F6); border: 1px solid var(--line-2, rgb(255 255 255 / .12)) !important }
    .gf-annuler:hover { background: #242734 !important }
    .gf-pop .swal2-actions button:focus-visible { outline: 3px solid rgb(229 9 20 / .45); outline-offset: 2px }
    .gf-pop .swal2-loader { border-color: #FF2E3B transparent #B91C1C transparent }

    /* Icônes : pastille colorée animée à la place des icônes par défaut */
    .gf-pop .swal2-icon.gf-icone { border: 0 !important; width: 72px; height: 72px; margin: 0 auto 18px; display: grid !important; place-items: center; border-radius: 22px; animation: gf-pastille .45s cubic-bezier(.2, .8, .2, 1) both, gf-onde 1.2s .35s ease-out }
    .gf-pop .swal2-icon.gf-icone .swal2-icon-content { font-size: 0; display: grid; place-items: center }
    .gf-icone svg { width: 34px; height: 34px }
    /* Spécificité renforcée : les couleurs par défaut de SweetAlert (.swal2-icon.swal2-warning…) ne doivent pas l'emporter */
    .swal2-icon.gf-icone.gf-i-succes, .swal2-icon.gf-icone.gf-i-erreur, .swal2-icon.gf-icone.gf-i-alerte, .swal2-icon.gf-icone.gf-i-question, .swal2-icon.gf-icone.gf-i-attente { color: var(--gf-i) !important }
    .gf-i-succes { --gf-i: #FF2E3B; background: linear-gradient(135deg, rgb(229 9 20 / .2), rgb(35 38 50 / .6)); color: #FF2E3B; --onde: rgb(229 9 20 / .35) }
    .gf-i-erreur { --gf-i: #EF4444; background: linear-gradient(135deg, rgb(239 68 68 / .2), rgb(45 20 22 / .6)); color: #EF4444; --onde: rgb(239 68 68 / .35) }
    .gf-i-alerte { --gf-i: #F59E0B; background: linear-gradient(135deg, rgb(245 158 11 / .2), rgb(45 35 20 / .6)); color: #F59E0B; --onde: rgb(245 158 11 / .35) }
    .gf-i-question { --gf-i: #E50914; background: linear-gradient(135deg, rgb(229 9 20 / .2), rgb(35 38 50 / .6)); color: #E50914; --onde: rgb(229 9 20 / .3) }
    .gf-i-attente { --gf-i: #FF4D58; background: linear-gradient(135deg, rgb(229 9 20 / .15), rgb(30 32 42 / .6)); color: #FF4D58; --onde: rgb(229 9 20 / .35) }

    .gf-pop .swal2-textarea, .gf-pop .swal2-input { margin: 18px 0 0; width: 100%; box-sizing: border-box; border: 1px solid var(--line-2, rgb(255 255 255 / .12)); border-radius: 14px; font: 500 14.5px var(--f-body, "DM Sans", sans-serif); color: var(--fg, #F3F4F6); background: var(--surface, #14151D); box-shadow: 0 1px 2px rgb(0 0 0 / .3); padding: 12px 14px; transition: border-color .2s, box-shadow .2s }
    .gf-pop .swal2-textarea { min-height: 96px }
    .gf-pop .swal2-textarea:focus, .gf-pop .swal2-input:focus { border-color: #E50914; box-shadow: 0 0 0 4px rgb(229 9 20 / .2) }
    .gf-pop .swal2-validation-message { background: var(--danger-soft, rgb(239 68 68 / .2)); color: #FCA5A5; border-radius: 12px; margin: 12px 0 0; font-weight: 600; font-size: 13px }
    .gf-pop .swal2-validation-message::before { background: var(--danger, #EF4444) }

    /* Notifications */
    .swal2-popup.gf-toast { border-radius: 16px; padding: 12px 16px 12px 12px; width: auto; max-width: min(420px, calc(100vw - 32px)); background: rgb(20 22 30 / .95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 20px 50px -18px rgb(0 0 0 / .7), 0 0 0 1px rgb(255 255 255 / .1); color: var(--fg, #F3F4F6) }
    .gf-toast .swal2-title { font: 600 14px var(--f-body, "DM Sans", sans-serif); color: var(--fg, #F3F4F6); margin: 0 0 0 10px; line-height: 1.4 }
    .gf-toast .swal2-icon.gf-icone { width: 36px; height: 36px; min-width: 36px; margin: 0; border: 0 !important; border-radius: 11px; display: grid !important; place-items: center; animation: gf-pastille .4s cubic-bezier(.2, .8, .2, 1) both }
    .gf-toast .swal2-icon.gf-icone .swal2-icon-content { font-size: 0; display: grid }
    .gf-toast .gf-icone svg { width: 20px; height: 20px }
    .gf-toast .swal2-timer-progress-bar-container { border-radius: 0 0 16px 16px }
    .gf-toast .swal2-timer-progress-bar { background: linear-gradient(90deg, #22C1A5, #6D5DFB); height: 3px }
    .gf-toast.gf-toast-error .swal2-timer-progress-bar { background: linear-gradient(90deg, #EF4444, #F59E0B) }
    .gf-toast-entree { animation: gf-toast-entree .4s cubic-bezier(.2, .8, .2, 1) both }
    .gf-toast-sortie { animation: gf-toast-sortie .25s ease-in both }

    /* Mise en page « fenêtre » : en-tête (icône + titre), corps, pied à droite, fermeture en coin */
    .swal2-popup.gf-pop { position: relative; padding: 0; text-align: left; border-radius: 24px; width: min(480px, calc(100vw - 32px)); overflow: visible }
    .gf-pop .gf-entete { grid-column: 1 / -1; display: flex; align-items: center; gap: 12px; padding: 18px 56px 18px 24px; border-bottom: 1px solid var(--line, #E6EAF0) }
    .gf-pop .gf-entete .swal2-title { font-size: 17px; text-align: left; margin: 0; padding: 0; line-height: 1.3 }
    .gf-pop .gf-entete .swal2-icon.gf-icone { width: 36px; height: 36px; min-width: 36px; margin: 0; border-radius: 11px; --onde: transparent }
    .gf-pop .gf-entete .gf-icone svg { width: 19px; height: 19px }
    .gf-pop .swal2-html-container { text-align: left; margin: 0; padding: 18px 24px 0 }
    .gf-pop .swal2-textarea, .gf-pop .swal2-input { margin: 14px 24px 0; width: calc(100% - 48px) }
    .gf-pop .swal2-validation-message { margin: 10px 24px 0 }
    .gf-pop .swal2-actions { justify-content: flex-end; flex-wrap: wrap; width: auto; margin: 20px 0 0; padding: 14px 24px; border-top: 1px solid var(--line, #E6EAF0); background: var(--surface-2, #F7F9FC); border-radius: 0 0 24px 24px }
    .gf-pop .swal2-actions button { flex: none; min-width: 112px; min-height: 44px; padding: 0 18px; border-radius: 12px }
    .gf-pop .swal2-close { position: absolute; top: -14px; right: -14px; width: 34px; height: 34px; border-radius: 9px; background: var(--fg, #0F172A); color: #fff; font-size: 0; display: grid; place-items: center; box-shadow: 0 8px 18px -6px rgb(15 23 42 / .6); transition: transform .2s cubic-bezier(.2, .8, .2, 1), background .2s; margin: 0; padding: 0 }
    .gf-pop .swal2-close:hover { transform: rotate(90deg); background: var(--danger, #C81E1E); color: #fff }
    .gf-pop .swal2-close:focus { box-shadow: 0 0 0 3px rgb(37 99 235 / .45) }
    @media (max-width: 560px) { .gf-pop .swal2-close { top: 10px; right: 10px } }

    @media (prefers-reduced-motion: reduce) { .gf-entree, .gf-sortie, .gf-toast-entree, .gf-toast-sortie, .gf-icone { animation: none !important } }
    @media print { .swal2-container { display: none !important } }
</style>
<script>
(() => {
    const svg = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
    const ICONES = {
        success: ['gf-i-succes', svg('<path d="M5 12.5l4.5 4.5L19 7.5"/>')],
        error: ['gf-i-erreur', svg('<path d="M7 7l10 10M17 7L7 17"/>')],
        warning: ['gf-i-alerte', svg('<path d="M12 8v5M12 16.5v.5"/><path d="M10.3 3.9L2.6 17.3A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.7L13.7 3.9a2 2 0 0 0-3.4 0z" stroke-width="2"/>')],
        question: ['gf-i-question', svg('<path d="M9.2 9.2a3 3 0 1 1 4.3 2.7c-.9.5-1.5 1.2-1.5 2.2v.4M12 17.5v.5"/>')],
        info: ['gf-i-attente', svg('<circle cx="12" cy="12" r="8" stroke-width="2"/><path d="M12 8v4l2.5 2"/>')],
    };
    // Remplace l'icône par défaut par une pastille colorée : on garde le type pour l'accessibilité
    const icone = (type) => type && ICONES[type] ? { icon: type, iconHtml: ICONES[type][1], customIconClass: ICONES[type][0] } : {};

    const base = Swal.mixin({
        customClass: { popup: 'gf-pop', confirmButton: 'gf-ok', cancelButton: 'gf-annuler', denyButton: 'gf-danger' },
        buttonsStyling: false,
        reverseButtons: true,
        showClass: { popup: 'gf-entree', backdrop: 'swal2-backdrop-show' },
        hideClass: { popup: 'gf-sortie' },

    });
    // Enveloppe qui convertit « icon » en pastille GymFlow
    const CROIX = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    // Regroupe l'icône et le titre dans un en-tête, comme les fenêtres de formulaire
    const entete = (p) => {
        const titre = p.querySelector('.swal2-title');
        if (!titre) return;
        let ent = p.querySelector('.gf-entete');
        if (!ent) { ent = document.createElement('div'); ent.className = 'gf-entete'; titre.before(ent); }
        const i = p.querySelector('.swal2-icon');
        if (i && getComputedStyle(i).display !== 'none') ent.append(i);
        ent.append(titre);
    };
    const fenetre = (options) => {
        const { icon, ...reste } = options;
        const conf = icone(icon);
        return base.fire({
            showCloseButton: true, closeButtonHtml: CROIX, closeButtonAriaLabel: 'Fermer',
            ...reste, ...(conf.icon ? { icon: conf.icon, iconHtml: conf.iconHtml } : {}),
            didOpen: (p) => {
                if (conf.customIconClass) p.querySelector('.swal2-icon')?.classList.add('gf-icone', conf.customIconClass);
                entete(p);
                reste.didOpen?.(p);
            },
        });
    };

    const toastBase = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 4200, timerProgressBar: true,
        showClass: { popup: 'gf-toast-entree' }, hideClass: { popup: 'gf-toast-sortie' },
    });
    const echapper = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    window.gfToast = (titre, type = 'success') => {
        const conf = icone(type);
        return toastBase.fire({
            title: titre, icon: conf.icon, iconHtml: conf.iconHtml,
            customClass: { popup: 'gf-toast' + (type === 'error' ? ' gf-toast-error' : '') },
            didOpen: (t) => {
                t.querySelector('.swal2-icon')?.classList.add('gf-icone', conf.customIconClass);
                t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });
    };
    // Accès pour les autres scripts : gfAlerte.fire({...}) accepte les mêmes options que Swal.fire
    window.gfAlerte = { fire: fenetre };

    // Confirmation avant envoi d'un formulaire
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.gfOk === '1') return;

        if (form.dataset.confirmer !== undefined) {
            if (form.dataset.confirmerSi && !form.querySelector(form.dataset.confirmerSi)?.value) return;
            e.preventDefault();
            const depuis = form.dataset.texteDepuis ? document.querySelector(form.dataset.texteDepuis)?.textContent.trim() : null;
            const texte = form.dataset.texte ? form.dataset.texte.replace('{texte}', depuis ?? '') : (depuis || '');
            const danger = form.dataset.variante === 'danger';
            const r = await fenetre({
                icon: danger ? 'warning' : 'question',
                title: form.dataset.confirmer || 'Confirmer ?',
                text: texte,
                showCancelButton: true,
                confirmButtonText: form.dataset.bouton || 'Confirmer',
                cancelButtonText: 'Annuler',
                customClass: { popup: 'gf-pop', confirmButton: danger ? 'gf-danger' : 'gf-ok', cancelButton: 'gf-annuler' },
            });
            if (r.isConfirmed) { form.dataset.gfOk = '1'; form.requestSubmit(e.submitter || undefined); delete form.dataset.gfOk; }
            return;
        }

        if (form.dataset.motif !== undefined) {
            e.preventDefault();
            const r = await fenetre({
                icon: 'warning',
                title: form.dataset.motif || 'Indiquez un motif',
                html: form.dataset.motifAide ? echapper(form.dataset.motifAide) : '',
                input: 'textarea',
                inputPlaceholder: form.dataset.motifExemple || 'Motif…',
                inputAttributes: { maxlength: 255, 'aria-label': 'Motif' },
                showCancelButton: true,
                confirmButtonText: form.dataset.bouton || 'Confirmer',
                cancelButtonText: 'Retour',
                customClass: { popup: 'gf-pop', confirmButton: 'gf-danger', cancelButton: 'gf-annuler' },
                inputValidator: (v) => (!v || v.trim().length < 3) ? 'Le motif doit faire au moins 3 caractères.' : undefined,
            });
            if (r.isConfirmed) {
                form.querySelector(`[name="${form.dataset.motifChamp || 'motif'}"]`).value = r.value.trim();
                form.dataset.gfOk = '1'; form.submit();
            }
        }
    }, true);

    // Messages de la session
    @if($flash ?? true)
        @if(session('succes'))
            window.gfToast(@json(session('succes')));
        @endif
        @if(session('erreur'))
            fenetre({ icon: 'error', title: 'Action impossible', text: @json(session('erreur')), confirmButtonText: 'Compris' });
        @elseif($errors->any())
            fenetre({
                icon: 'error',
                title: @json($errors->count() > 1 ? 'Vérifiez ces informations' : 'Vérifiez cette information'),
                html: '<ul>' + @json($errors->all()).map((m) => '<li>' + echapper(m) + '</li>').join('') + '</ul>',
                confirmButtonText: 'Corriger',
            });
        @endif
    @endif
})();
</script>
