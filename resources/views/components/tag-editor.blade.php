{{-- Modern tag editor: pills + autocomplete. Works without Alpine (plain JS).
     Props: $name (hidden input name), $value (comma-separated preset),
            $inputId (hidden input id, defaults to $name),
            $suggestUrl (autocomplete endpoint), $placeholder,
            $styles (map of lowercase tag name → {background, border, text}
            for presetting pill colors, e.g. from saved categories). --}}
@props([
    'name' => 'tags',
    'value' => '',
    'inputId' => null,
    'suggestUrl' => null,
    'placeholder' => 'Digite uma tag e pressione Enter…',
    'styles' => [],
])
@php($inputId ??= $name)
@php($suggestUrl ??= route('api.tags'))

<div class="tag-editor relative" data-input-id="{{ $inputId }}" data-suggest-url="{{ $suggestUrl }}" data-styles='@json($styles)'>
    <input type="hidden" id="{{ $inputId }}" name="{{ $name }}" value="{{ $value }}">

    <div class="tag-pills flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-950 px-2 py-1.5 focus-within:ring-2 focus-within:ring-indigo-500/30">
        <div class="tag-list contents"></div>
        <input type="text"
               class="tag-box min-w-32 flex-1 bg-transparent px-1 py-1 text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               aria-label="Tags">
    </div>
    <ul class="tag-suggest absolute z-20 mt-1 hidden max-h-56 w-full overflow-y-auto rounded-lg border border-zinc-700 bg-zinc-900 py-1 shadow-xl"></ul>
    <p class="mt-1 text-xs text-zinc-500">Enter, vírgula ou Tab adiciona · Backspace remove a última</p>
</div>

<script>
(function () {
    document.querySelectorAll('.tag-editor').forEach(function (root) {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';

        var hidden = root.querySelector('input[type="hidden"]');
        var box = root.querySelector('.tag-box');
        var list = root.querySelector('.tag-list');
        var suggest = root.querySelector('.tag-suggest');
        var suggestUrl = root.dataset.suggestUrl;
        var tags = (hidden.value || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
        var activeIdx = -1;
        var debounce = null;

        // Pill colors by category (lowercase name → {background, border, text}).
        var styleMap = {};
        try {
            var preset = JSON.parse(root.dataset.styles || '{}');
            Object.keys(preset || {}).forEach(function (k) {
                if (preset[k]) styleMap[String(k).toLowerCase()] = preset[k];
            });
        } catch (e) { /* unstyled */ }

        function cssTextFor(name) {
            var s = styleMap[String(name).toLowerCase()];
            if (!s) return '';
            var parts = [];
            if (s.background) parts.push('background-color:' + s.background);
            if (s.border) parts.push('border-color:' + s.border);
            if (s.text) parts.push('color:' + s.text);
            return parts.join(';');
        }

        function sync() {
            hidden.value = tags.join(', ');
        }

        function pill(name) {
            var el = document.createElement('span');
            el.className = 'inline-flex items-center gap-1 rounded-full bg-indigo-600/20 px-2.5 py-0.5 text-xs font-medium text-indigo-200 ring-1 ring-inset ring-indigo-500/40';
            var css = cssTextFor(name);
            if (css) el.style.cssText = css + ';border-width:1px;border-style:solid;';
            el.textContent = name;
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'ml-0.5 rounded-full px-1 leading-none text-indigo-300 hover:bg-indigo-500/30 hover:text-white';
            x.textContent = '×';
            x.setAttribute('aria-label', 'Remover tag ' + name);
            x.addEventListener('click', function () {
                tags = tags.filter(function (t) { return t !== name; });
                sync();
                render();
                box.focus();
            });
            el.appendChild(x);
            return el;
        }

        function render() {
            list.innerHTML = '';
            tags.forEach(function (t) { list.appendChild(pill(t)); });
        }

        function add(raw, style) {
            var name = (raw || '').replace(/,/g, '').trim().slice(0, 50);
            if (!name) return;
            if (tags.some(function (t) { return t.toLowerCase() === name.toLowerCase(); })) return;
            if (style) styleMap[name.toLowerCase()] = style;
            tags.push(name);
            sync();
            render();
        }

        function closeSuggest() {
            suggest.classList.add('hidden');
            suggest.innerHTML = '';
            activeIdx = -1;
        }

        function pick(idx) {
            var items = suggest.querySelectorAll('li');
            if (items[idx]) {
                add(items[idx].dataset.name, categoryOf(items[idx]));
                box.value = '';
                closeSuggest();
                box.focus();
            }
        }

        function categoryOf(li) {
            try {
                return JSON.parse(li.dataset.category || 'null');
            } catch (e) {
                return null;
            }
        }

        function fetchSuggest(q) {
            clearTimeout(debounce);
            if (q.length < 1) { closeSuggest(); return; }
            debounce = setTimeout(function () {
                fetch(suggestUrl + '?q=' + encodeURIComponent(q) + '&limit=8', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .then(function (data) {
                        var fresh = data.filter(function (t) {
                            return !tags.some(function (mine) { return mine.toLowerCase() === String(t.name).toLowerCase(); });
                        }).slice(0, 8);
                        if (!fresh.length) { closeSuggest(); return; }
                        suggest.innerHTML = '';
                        fresh.forEach(function (t) {
                            var li = document.createElement('li');
                            li.dataset.name = t.name;
                            li.dataset.category = JSON.stringify(t.category || null);
                            li.className = 'cursor-pointer px-3 py-1.5 text-sm text-zinc-200 hover:bg-indigo-600 hover:text-white';
                            if (t.category) {
                                if (t.category.text) li.style.color = t.category.text;
                                if (t.category.background) li.style.backgroundColor = t.category.background;
                                if (t.category.border) li.style.borderLeft = '3px solid ' + t.category.border;
                            }
                            li.textContent = t.name;
                            li.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                add(t.name, t.category || null);
                                box.value = '';
                                closeSuggest();
                            });
                            suggest.appendChild(li);
                        });
                        activeIdx = -1;
                        suggest.classList.remove('hidden');
                    })
                    .catch(closeSuggest);
            }, 200);
        }

        box.addEventListener('keydown', function (e) {
            var items = suggest.querySelectorAll('li');
            var open = !suggest.classList.contains('hidden') && items.length > 0;
            if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
                e.preventDefault();
                if (open && activeIdx >= 0) pick(activeIdx);
                else { add(box.value); box.value = ''; closeSuggest(); }
            } else if (e.key === 'ArrowDown' && open) {
                e.preventDefault();
                activeIdx = (activeIdx + 1) % items.length;
                highlight(items);
            } else if (e.key === 'ArrowUp' && open) {
                e.preventDefault();
                activeIdx = (activeIdx - 1 + items.length) % items.length;
                highlight(items);
            } else if (e.key === 'Escape') {
                closeSuggest();
            } else if (e.key === 'Backspace' && box.value === '' && tags.length) {
                tags.pop();
                sync();
                render();
            }
        });

        function highlight(items) {
            items.forEach(function (li, i) {
                li.classList.toggle('bg-indigo-600', i === activeIdx);
                li.classList.toggle('text-white', i === activeIdx);
            });
        }

        box.addEventListener('input', function () {
            // Comma pasted or typed mid-string commits the token.
            if (box.value.indexOf(',') !== -1) {
                box.value.split(',').forEach(add);
                box.value = '';
                closeSuggest();
                return;
            }
            fetchSuggest(box.value.trim());
        });

        box.addEventListener('blur', function () {
            setTimeout(closeSuggest, 120);
        });

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) closeSuggest();
        });

        // External providers (e.g. the E-Hentai tag lookup) inject
        // fetched tags via: document.dispatchEvent(new CustomEvent(
        // 'tags:inject', { detail: { inputId, tags: [...] } })).
        document.addEventListener('tags:inject', function (e) {
            if (!e.detail || e.detail.inputId !== hidden.id) return;
            (e.detail.tags || []).forEach(add);
            box.value = '';
            closeSuggest();
            box.focus();
        });

        sync();
        render();
    });
})();
</script>
