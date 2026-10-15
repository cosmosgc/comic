{{-- External tag lookup (E-Hentai API): paste a gallery/page URL or type a
      title, fetch web tags, auto-check the ones matching local tags.
      Props: $tagsInputId (hidden input id of the tag-editor),
             $lookupUrl (GET /api/tags/lookup), $limit,
             $titleInputId / $authorInputId (optional ids of the comic
             title + artist fields; enables the "suggest from fields"
             button that searches the web using both). --}}
@props([
    'tagsInputId' => 'tags',
    'lookupUrl' => null,
    'limit' => 3,
    'titleInputId' => null,
    'authorInputId' => null,
])
@php($lookupUrl ??= route('api.tags.lookup'))

<div class="tag-lookup rounded-xl border border-zinc-800 bg-zinc-950 p-4"
     data-tags-input="{{ $tagsInputId }}"
     data-lookup-url="{{ $lookupUrl }}"
     data-limit="{{ $limit }}"
     data-title-input="{{ $titleInputId }}"
     data-author-input="{{ $authorInputId }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-zinc-200">Buscar tags na web (E-Hentai)</p>
        <span class="lookup-status text-xs text-zinc-500"></span>
    </div>
    <p class="mt-1 text-xs text-zinc-500">Cole a URL da galeria (<code>…/g/1234567/abcd…/</code>), digite o título{{ ($titleInputId || $authorInputId) ? ', ou use o botão de sugestão pelo título + autor' : '' }}. Tags que já existem aqui são marcadas em verde.</p>

    <div class="mt-3 flex flex-wrap gap-2">
        <input type="text"
               class="lookup-query min-w-0 flex-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
               placeholder="https://e-hentai.org/g/…/…/ ou título do comic"
               autocomplete="off">
        <button type="button"
                class="lookup-btn shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-50">
            Buscar
        </button>
        @if ($titleInputId || $authorInputId)
            <button type="button"
                    class="lookup-suggest shrink-0 rounded-lg border border-indigo-500/40 bg-indigo-600/15 px-4 py-2 text-sm font-semibold text-indigo-200 hover:bg-indigo-600/25 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-50"
                    title="Monta a busca com o título e o autor preenchidos acima">
                Sugerir por título + autor
            </button>
        @endif
    </div>

    <div class="lookup-results mt-3 hidden space-y-3"></div>

    <div class="lookup-apply-row mt-3 hidden">
        <button type="button"
                class="lookup-apply rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
            Aplicar selecionadas
        </button>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.tag-lookup').forEach(function (root) {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';

        var query = root.querySelector('.lookup-query');
        var btn = root.querySelector('.lookup-btn');
        var status = root.querySelector('.lookup-status');
        var results = root.querySelector('.lookup-results');
        var applyRow = root.querySelector('.lookup-apply-row');
        var applyBtn = root.querySelector('.lookup-apply');
        var lookupUrl = root.dataset.lookupUrl;
        var limit = root.dataset.limit || '3';
        var tagsInputId = root.dataset.tagsInput;
        var suggestBtn = root.querySelector('.lookup-suggest');

        // Suggestion search: compose the web query from the comic's own
        // title + artist fields (e.g. "Kininaru Danshi Gentsuki").
        function fieldValue(id) {
            if (!id) return '';
            var el = document.getElementById(id);
            return el ? (el.value || '').trim() : '';
        }

        function suggestedQuery() {
            return [fieldValue(root.dataset.titleInput), fieldValue(root.dataset.authorInput)]
                .filter(function (v) { return v !== ''; })
                .join(' ');
        }

        function setStatus(text, isError) {
            status.textContent = text || '';
            status.className = 'lookup-status text-xs ' + (isError ? 'text-red-400' : 'text-zinc-500');
        }

        function tagCheck(t) {
            var label = document.createElement('label');
            label.className = 'inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' +
                (t.matched
                    ? 'bg-emerald-600/15 text-emerald-200 ring-emerald-500/40'
                    : 'bg-zinc-800 text-zinc-300 ring-zinc-700 hover:bg-zinc-700');
            // Category colors (Flask-style) override the neutral look.
            if (t.category) {
                var css = [];
                if (t.category.background) css.push('background-color:' + t.category.background);
                if (t.category.border) css.push('border-color:' + t.category.border);
                if (t.category.text) css.push('color:' + t.category.text);
                if (css.length) label.style.cssText = css.join(';') + ';border-width:1px;border-style:solid;';
            }
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = t.matched && t.local ? t.local.name : t.full;
            cb.checked = !!t.matched;
            cb.className = 'h-3.5 w-3.5 accent-emerald-500';
            var span = document.createElement('span');
            span.textContent = t.full + (t.matched && t.local && t.local.name !== t.full ? ' → ' + t.local.name : '');
            label.appendChild(cb);
            label.appendChild(span);
            return label;
        }

        function render(data) {
            results.innerHTML = '';
            var galleries = data.galleries || [];
            if (!galleries.length) {
                results.classList.remove('hidden');
                results.innerHTML = '<p class="text-sm text-zinc-400">Nenhuma galeria encontrada.</p>';
                applyRow.classList.add('hidden');
                return;
            }
            galleries.forEach(function (g, i) {
                var card = document.createElement('div');
                card.className = 'rounded-lg border border-zinc-800 bg-zinc-900 p-3';
                var head = document.createElement('div');
                head.className = 'mb-2 flex items-start gap-3';
                if (g.thumb) {
                    var img = document.createElement('img');
                    img.src = g.thumb;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.className = 'h-16 w-12 rounded object-cover';
                    head.appendChild(img);
                }
                var meta = document.createElement('div');
                meta.className = 'min-w-0';
                var title = document.createElement('p');
                title.className = 'truncate text-sm font-medium text-zinc-100';
                title.textContent = g.title || ('Galeria ' + (g.gid || (i + 1)));
                meta.appendChild(title);
                var sub = document.createElement('p');
                sub.className = 'text-xs text-zinc-500';
                sub.textContent = [g.category, g.rating ? '★ ' + g.rating : null, g.filecount ? g.filecount + ' arqs' : null].filter(Boolean).join(' · ');
                meta.appendChild(sub);
                if (g.url) {
                    var link = document.createElement('a');
                    link.href = g.url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'text-xs text-indigo-400 hover:text-indigo-300';
                    link.textContent = 'abrir no E-Hentai ↗';
                    meta.appendChild(link);
                }
                if (g.error) {
                    var err = document.createElement('p');
                    err.className = 'text-xs text-red-400';
                    err.textContent = g.error;
                    meta.appendChild(err);
                }
                head.appendChild(meta);
                card.appendChild(head);

                var controls = document.createElement('div');
                controls.className = 'mb-2 flex items-center gap-2';
                var toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'rounded-md border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-xs font-medium text-zinc-300 hover:bg-zinc-700';
                var count = document.createElement('span');
                count.className = 'text-xs text-zinc-500';
                controls.appendChild(toggleBtn);
                controls.appendChild(count);
                card.appendChild(controls);

                var box = document.createElement('div');
                box.className = 'flex flex-wrap gap-1.5';
                (g.tags || []).forEach(function (t) { box.appendChild(tagCheck(t)); });
                if (!(g.tags || []).length && !g.error) {
                    box.innerHTML = '<span class="text-xs text-zinc-500">Sem tags.</span>';
                }
                card.appendChild(box);

                // Per-gallery select-all toggle: selects every tag in this
                // result, or clears them when all are already checked.
                var boxes = function () { return Array.prototype.slice.call(box.querySelectorAll('input[type="checkbox"]')); };
                function refreshToggle() {
                    var all = boxes();
                    var checked = all.filter(function (cb) { return cb.checked; }).length;
                    toggleBtn.textContent = (all.length && checked === all.length) ? 'Desmarcar todas' : 'Selecionar todas';
                    count.textContent = all.length ? checked + '/' + all.length + ' selecionadas' : '';
                }
                toggleBtn.addEventListener('click', function () {
                    var all = boxes();
                    var target = !all.every(function (cb) { return cb.checked; });
                    all.forEach(function (cb) { cb.checked = target; });
                    refreshToggle();
                });
                box.addEventListener('change', refreshToggle);
                refreshToggle();
                results.appendChild(card);
            });
            var matched = (data.matched_names || []).length;
            setStatus(matched ? matched + ' tag(s) local(is) encontrada(s) — pré-marcadas' : 'Nenhuma tag local correspondente');
            results.classList.remove('hidden');
            applyRow.classList.remove('hidden');
        }

        function run() {
            var q = query.value.trim();
            if (!q) {
                // Empty box: fall back to the comic's title + artist.
                q = suggestedQuery();
                if (q) query.value = q;
            }
            if (!q) { setStatus('Digite uma URL/título ou preencha o título/autor acima.', true); return; }
            btn.disabled = true;
            setStatus('Buscando…');
            results.classList.add('hidden');
            applyRow.classList.add('hidden');
            fetch(lookupUrl + '?q=' + encodeURIComponent(q) + '&limit=' + encodeURIComponent(limit), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                .then(function (res) {
                    btn.disabled = false;
                    if (!res.ok) {
                        setStatus(res.body.message || 'Falha na busca.', true);
                        return;
                    }
                    render(res.body);
                })
                .catch(function () {
                    btn.disabled = false;
                    setStatus('Erro de rede ao buscar.', true);
                });
        }

        btn.addEventListener('click', run);
        query.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); run(); }
        });

        if (suggestBtn) {
            suggestBtn.addEventListener('click', function () {
                var q = suggestedQuery();
                if (!q) { setStatus('Preencha o título ou o autor do comic para sugerir.', true); return; }
                query.value = q;
                run();
            });
        }

        applyBtn.addEventListener('click', function () {
            var names = [];
            results.querySelectorAll('input[type="checkbox"]:checked').forEach(function (cb) {
                if (cb.value.trim()) names.push(cb.value.trim());
            });
            if (!names.length) { setStatus('Marque ao menos uma tag.', true); return; }
            document.dispatchEvent(new CustomEvent('tags:inject', {
                detail: { inputId: tagsInputId, tags: names }
            }));
            setStatus(names.length + ' tag(s) aplicada(s) ✓');
        });
    });
})();
</script>
