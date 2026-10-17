{{-- Shared front-end interactions: profile hover cards, the follow
     toggle and the gamification XP toast. Included by every public
     layout (the site and the detached forum) so the behaviour lives
     in exactly one place. --}}

{{-- Profile hover cards. --}}
<script>
const HOVERCARD_API = "{{ url('api/users') }}";
(function () {
    const cache = new Map();
    let card = null;
    let showTimer = null;
    let hideTimer = null;
    let currentName = null;

    function ensureCard() {
        if (card) return card;
        card = document.createElement('div');
        card.id = 'profile-hovercard';
        card.className = 'fixed z-[90] hidden w-72 overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-2xl';
        card.addEventListener('mouseenter', function () {
            clearTimeout(hideTimer);
        });
        card.addEventListener('mouseleave', scheduleHide);
        document.body.appendChild(card);
        return card;
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(data) {
        const el = ensureCard();
        const banner = data.banner_url
            ? '<img src="' + esc(data.banner_url) + '" alt="" class="absolute inset-0 h-full w-full object-cover">'
            : '';
        el.innerHTML =
            '<div class="relative h-20 bg-gradient-to-r from-indigo-900 via-zinc-900 to-zinc-900">' + banner + '</div>' +
            '<div class="px-3 pb-3">' +
                '<img src="' + esc(data.avatar_url) + '" alt="" class="relative z-10 -mt-7 h-14 w-14 rounded-full border-4 border-zinc-900 object-cover">' +
                '<a href="' + esc(data.profile_url) + '" class="mt-1 block truncate font-bold text-zinc-100 hover:underline">' + esc(data.name) + '</a>' +
                (data.bio ? '<p class="mt-0.5 line-clamp-2 text-xs text-zinc-400">' + esc(data.bio) + '</p>' : '') +
                '<p class="mt-1 text-xs">' +
                    (data.level ? '<span class="rounded-full bg-indigo-500/20 px-1.5 py-0.5 font-bold text-indigo-300">Nv ' + data.level + '</span>' : '') +
                    (data.badge ? ' <span title="' + esc(data.badge.label) + '">' + esc(data.badge.icon) + ' ' + esc(data.badge.label) + '</span>' : '') +
                '</p>' +
                '<p class="mt-2 text-xs text-zinc-500">' +
                    '<span class="font-semibold text-zinc-300">' + (data.comics_count ?? 0) + '</span> Comics · ' +
                    '<span class="font-semibold text-zinc-300">' + (data.posts_count ?? 0) + '</span> Posts' +
                '</p>' +
                (data.follows_enabled && !data.is_self && data.follow_url
                    ? '<button type="button" data-style="chip" data-following="' + (data.following ? '1' : '0') + '" ' +
                      'onclick="toggleFollow(\'' + esc(data.follow_url) + '\', this)" ' +
                      'class="mt-2 inline-flex items-center rounded-lg px-3 py-1 text-xs font-semibold transition ' +
                      (data.following
                          ? 'border border-zinc-700 text-zinc-200 hover:border-rose-700 hover:text-rose-300'
                          : 'bg-indigo-600 text-white hover:bg-indigo-500') + '">' +
                      (data.following ? 'Seguindo' : 'Seguir') + '</button>'
                    : '') +
            '</div>';
    }

    function place(anchor) {
        const el = ensureCard();
        const rect = anchor.getBoundingClientRect();
        const W = 288, H = el.offsetHeight || 220, GAP = 8;
        let left = Math.min(Math.max(8, rect.left), window.innerWidth - W - 8);
        let top = rect.bottom + GAP;
        if (top + H > window.innerHeight - 8) {
            top = Math.max(8, rect.top - H - GAP);
        }
        el.style.left = left + 'px';
        el.style.top = top + 'px';
    }

    function show(anchor) {
        const name = anchor.getAttribute('data-hovercard');
        if (!name) return;
        currentName = name;
        const render2 = function (data) {
            if (currentName !== name) return;
            render(data);
            const el = ensureCard();
            el.classList.remove('hidden');
            place(anchor);
        };
        if (cache.has(name)) {
            render2(cache.get(name));
            return;
        }
        fetch(HOVERCARD_API + '/' + encodeURIComponent(name) + '/card', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                cache.set(name, data);
                render2(data);
            })
            .catch(function () {});
    }

    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function () {
            if (card) card.classList.add('hidden');
            currentName = null;
        }, 200);
    }

    document.addEventListener('mouseover', function (e) {
        const anchor = e.target.closest ? e.target.closest('[data-hovercard]') : null;
        if (!anchor) return;
        clearTimeout(hideTimer);
        clearTimeout(showTimer);
        const name = anchor.getAttribute('data-hovercard');
        showTimer = setTimeout(function () { show(anchor); }, name && cache.has(name) ? 120 : 350);
    });

    document.addEventListener('mouseout', function (e) {
        const anchor = e.target.closest ? e.target.closest('[data-hovercard]') : null;
        if (!anchor) return;
        clearTimeout(showTimer);
        if (card && !card.matches(':hover')) scheduleHide();
    });

    document.addEventListener('scroll', function () {
        if (card) card.classList.add('hidden');
    }, { passive: true, capture: true });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && card) card.classList.add('hidden');
    });
})();
</script>

{{-- AJAX XP pop: controllers include the award in JSON responses. --}}
<script>
    window.openXpToast = function (payload) {
        if (!payload) return;
        var hasExp = (payload.exp || 0) > 0;
        if (!hasExp && !payload.leveled_up && !(payload.badges && payload.badges.length)) return;

        var box = document.createElement('div');
        box.className = 'pointer-events-none fixed bottom-5 right-5 z-[96] w-72 max-w-[calc(100vw-2rem)]';
        box.style.transition = 'opacity .3s ease, transform .3s ease';
        box.style.opacity = '0';
        box.style.transform = 'translateY(1rem)';

        var html = '<div style="border-radius:1rem;border:1px solid rgba(99,102,241,.4);'
            + 'background:rgba(24,24,27,.97);padding:1rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);'
            + 'color:#e4e4e7;font-family:sans-serif;">';
        if (hasExp) {
            html += '<div style="display:flex;align-items:center;gap:.5rem;">'
                + '<span style="font-size:1.5rem;">⚡</span><div>'
                + '<p style="margin:0;font-weight:700;color:#a5b4fc;">+' + payload.exp + ' XP</p>'
                + '<p style="margin:0;font-size:.75rem;color:#a1a1aa;">Experiência ganha!</p></div></div>';
        }
        if (payload.leveled_up) {
            html += '<div style="margin-top:.5rem;border-radius:.5rem;background:rgba(79,70,229,.2);padding:.5rem .75rem;">'
                + '<p style="margin:0;font-size:.85rem;font-weight:600;color:#c7d2fe;">🎉 Subiu para o nível '
                + payload.level + '!</p></div>';
        }
        (payload.badges || []).forEach(function (b) {
            html += '<div style="margin-top:.5rem;border-radius:.5rem;background:rgba(245,158,11,.15);padding:.5rem .75rem;">'
                + '<p style="margin:0;font-size:.75rem;font-weight:600;color:#fde68a;">'
                + (b.icon || '🏅') + ' Conquista: ' + (b.label || '') + '</p></div>';
        });
        html += '</div>';
        box.innerHTML = html;
        document.body.appendChild(box);

        requestAnimationFrame(function () {
            box.style.opacity = '1';
            box.style.transform = 'translateY(0)';
        });
        setTimeout(function () {
            box.style.opacity = '0';
            box.style.transform = 'translateY(.5rem)';
            setTimeout(function () { box.remove(); }, 300);
        }, 5200);
    };
</script>

{{-- Gamification XP pop (flashed by App\Services\Gamification). --}}
@include('components.xp-toast')

{{-- Follow toggle: used by profiles, the hover card and user lists. --}}
<script>
    window.toggleFollow = function (url, btn) {
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data) return;
            var on = !!data.following;
            btn.dataset.following = on ? '1' : '0';
            if (btn.dataset.style === 'chip') {
                btn.textContent = on ? 'Seguindo' : 'Seguir';
                btn.classList.toggle('bg-indigo-600', !on);
                btn.classList.toggle('text-white', !on);
                btn.classList.toggle('border', on);
                btn.classList.toggle('border-zinc-700', on);
                btn.classList.toggle('text-zinc-200', on);
            }
            document.querySelectorAll('[data-followers-count]').forEach(function (el) {
                el.textContent = data.followers;
            });
        }).catch(function () {});
    };
</script>
