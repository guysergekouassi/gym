{{-- Recherche universelle : Ctrl + K ou « / » depuis n'importe quel écran --}}
@php
    $raccourcis = auth()->user()->isCaissier()
        ? [
            ['Nouveau client', route('clients.create')],
            ['Entrée journalière', route('caisse.index').'#journalier'],
            ['Clôturer la caisse', route('caisse.cloture')],
            ['Passages du jour', route('passages.index')],
            ['À faire', route('taches.index')],
            ['Nouveau prospect', route('prospects.index').'#nouveau'],
        ]
        : [
            ['Nouveau client', route('clients.create')],
            ['Tableau de bord', route('dashboard')],
            ['Rapports', route('admin.rapports')],
            ['Nouvelle campagne', route('admin.campagnes.create')],
            ['Exports Excel', route('admin.exports')],
            ['Journal des actions', route('admin.journal')],
        ];
@endphp
<div class="cmdk-fond" data-recherche hidden>
    <div class="cmdk" role="dialog" aria-modal="true" aria-label="Rechercher un client">
        <label class="cmdk-champ" for="cmdk-q">
            @include('partials.icone', ['nom' => 'loupe'])
            <input id="cmdk-q" type="search" autocomplete="off" placeholder="Nom, téléphone, n° d'empreinte ou de carte">
            <kbd>Échap</kbd>
        </label>
        <ul class="cmdk-liste" data-resultats role="listbox" aria-label="Résultats"></ul>
        <div class="cmdk-pied">↑ ↓ pour choisir · Entrée pour ouvrir · Tab pour les actions</div>
    </div>
</div>
<script>
(() => {
    const URL = @json(route('recherche'));
    const ACTIONS = @json($raccourcis);
    const fond = document.querySelector('[data-recherche]');
    const champ = document.getElementById('cmdk-q');
    const liste = fond.querySelector('[data-resultats]');
    let items = [], sel = 0, minuteur = null, precedent = null;

    const ouvrir = () => { precedent = document.activeElement; fond.hidden = false; champ.value = ''; afficher([]); champ.focus(); };
    const fermer = () => { fond.hidden = true; precedent?.focus?.(); };

    function afficher(clients) {
        const q = champ.value.trim().toLowerCase();
        const actions = ACTIONS.filter(([l]) => !q || l.toLowerCase().includes(q));
        items = [...clients.map((c) => ({ url: c.actions[0].url })), ...actions.map(([, u]) => ({ url: u }))];
        sel = Math.min(sel, Math.max(0, items.length - 1));
        liste.replaceChildren();
        let i = 0;
        if (clients.length) liste.append(groupe('Membres'));
        clients.forEach((c) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option'); li.dataset.i = i++;
            const av = document.createElement('div'); av.className = 'av'; av.textContent = c.initiales || '?';
            const corps = document.createElement('div'); corps.className = 'grow';
            const nom = document.createElement('div'); nom.className = 'name'; nom.textContent = c.nom + ' ';
            const tag = document.createElement('span'); tag.className = 'tag ' + c.classe; tag.textContent = c.statut; nom.append(tag);
            const meta = document.createElement('div'); meta.className = 'meta';
            meta.textContent = [c.telephone, c.empreinte ? `Empreinte n° ${c.empreinte}` : null].filter(Boolean).join(' · ');
            const acts = document.createElement('div'); acts.className = 'cmdk-actions';
            c.actions.forEach((a) => { const l = document.createElement('a'); l.href = a.url; l.textContent = a.libelle; acts.append(l); });
            corps.append(nom, meta, acts); li.append(av, corps);
            li.addEventListener('click', (e) => { if (!e.target.closest('a')) location.href = c.actions[0].url; });
            liste.append(li);
        });
        if (actions.length) liste.append(groupe('Aller à'));
        actions.forEach(([libelle, url]) => {
            const li = document.createElement('li'); li.setAttribute('role', 'option'); li.dataset.i = i++;
            const a = document.createElement('a'); a.href = url; a.className = 'name'; a.textContent = '→ ' + libelle;
            li.append(a); liste.append(li);
        });
        if (!items.length) { const li = document.createElement('li'); li.className = 'empty'; li.textContent = 'Aucun résultat. Essayez un nom, un téléphone ou un numéro d’empreinte.'; liste.append(li); }
        surligner();
    }
    const groupe = (t) => { const li = document.createElement('li'); li.className = 'cmdk-groupe'; li.textContent = t; return li; };
    const surligner = () => liste.querySelectorAll('[data-i]').forEach((li) => li.classList.toggle('on', +li.dataset.i === sel));

    champ.addEventListener('input', () => {
        clearTimeout(minuteur); sel = 0;
        const q = champ.value.trim();
        if (q.length < 2) { afficher([]); return; }
        minuteur = setTimeout(async () => {
            try {
                const r = await fetch(`${URL}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                afficher((await r.json()).clients);
            } catch (e) { console.error('Recherche impossible :', e); afficher([]); }
        }, 200);
    });
    champ.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { sel = (sel + 1) % Math.max(1, items.length); surligner(); e.preventDefault(); }
        if (e.key === 'ArrowUp') { sel = (sel - 1 + Math.max(1, items.length)) % Math.max(1, items.length); surligner(); e.preventDefault(); }
        if (e.key === 'Enter' && items[sel]) { location.href = items[sel].url; e.preventDefault(); }
    });
    fond.addEventListener('click', (e) => { if (e.target === fond) fermer(); });
    document.addEventListener('keydown', (e) => {
        const saisie = /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName);
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); fond.hidden ? ouvrir() : fermer(); }
        else if (e.key === '/' && !saisie && fond.hidden) { e.preventDefault(); ouvrir(); }
        else if (e.key === 'Escape' && !fond.hidden) fermer();
    });
    document.querySelectorAll('[data-ouvrir-recherche]').forEach((b) => b.addEventListener('click', ouvrir));
})();
</script>
