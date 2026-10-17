@extends('layouts.admin-layout')

@section('title', 'Gamificação')
@section('page-title', 'Gamificação')

@section('content')
<div class="space-y-6">

    @if (session('success'))
        <div class="rounded-lg border border-green-700 bg-green-900/30 px-4 py-3 text-sm text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-sm text-red-200">
            {{ session('error') }}
        </div>
    @endif
    @if (session('migrate_output'))
        <pre class="overflow-x-auto rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-xs text-zinc-300">{{ session('migrate_output') }}</pre>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['users' => 'Usuários', 'comics' => 'Comics', 'posts' => 'Posts', 'comments' => 'Comentários'] as $key => $label)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totals[$key]) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Config --}}
    <form method="POST" action="{{ route('admin.gamification.settings') }}"
          class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        @csrf
        <div>
            <h2 class="text-lg font-bold">Configuração</h2>
            <p class="text-sm text-zinc-500">XP por ação, curva de níveis, sequência e conquistas. Salvo no banco (sobrepõe o arquivo).</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="text-sm font-medium">Base de XP por nível</span>
                <input type="number" name="level_base" min="1" max="1000000" value="{{ old('level_base', $levelBase) }}"
                       class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
            </label>
            <label class="block">
                <span class="text-sm font-medium">Curva de níveis</span>
                <select name="level_curve"
                        class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    @foreach ($curves as $value => $label)
                        <option value="{{ $value }}" @selected(old('level_curve', $levelCurve) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium">Bônus por dia de sequência</span>
                <input type="number" name="streak_bonus" min="0" max="1000" value="{{ old('streak_bonus', $streakBonus) }}"
                       class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
            </label>
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $enabled))
                   class="h-4 w-4 rounded border-zinc-400 text-indigo-600">
            <span class="text-sm font-medium">Gamificação ativa</span>
        </label>

        {{-- Exp per action --}}
        <div>
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">XP por ação</h3>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($actions as $action => $label)
                    <label class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                        <span class="min-w-0 truncate text-sm" title="{{ $action }}">{{ $label }}</span>
                        <input type="number" name="exp[{{ $action }}]" min="0" max="100000"
                               value="{{ old('exp.'.$action, $exp[$action] ?? 0) }}"
                               class="w-20 rounded-md border border-zinc-300 bg-white px-2 py-1 text-right text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Unique actions --}}
        <div>
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Só pagam uma vez por item</h3>
            <div class="flex flex-wrap gap-3">
                @foreach ($actions as $action => $label)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="unique_subject_actions[]" value="{{ $action }}"
                               @checked(in_array($action, old('unique_subject_actions', $uniqueActions), true))
                               class="h-4 w-4 rounded border-zinc-400 text-indigo-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Badges --}}
        <div>
            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Conquistas</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="text-left text-xs uppercase text-zinc-500">
                        <tr>
                            <th class="px-2 py-1">Chave</th>
                            <th class="px-2 py-1">Nome</th>
                            <th class="px-2 py-1">Ícone</th>
                            <th class="px-2 py-1">Métrica</th>
                            <th class="px-2 py-1">Limite</th>
                            <th class="px-2 py-1">Remover</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($badges as $key => $def)
                            <tr class="border-t border-zinc-200 dark:border-zinc-800">
                                <td class="px-2 py-1 font-mono text-xs text-zinc-500">{{ $key }}</td>
                                <td class="px-2 py-1">
                                    <input type="text" name="badges[{{ $key }}][label]" maxlength="100"
                                           value="{{ old('badges.'.$key.'.label', $def['label'] ?? $key) }}"
                                           class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="px-2 py-1">
                                    <input type="text" name="badges[{{ $key }}][icon]" maxlength="16"
                                           value="{{ old('badges.'.$key.'.icon', $def['icon'] ?? '🏅') }}"
                                           class="w-16 rounded-md border border-zinc-300 bg-white px-2 py-1 text-center text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="px-2 py-1">
                                    <select name="badges[{{ $key }}][metric]"
                                            class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                        @foreach ($metrics as $metric => $metricLabel)
                                            <option value="{{ $metric }}" @selected(old('badges.'.$key.'.metric', $def['metric'] ?? '') === $metric)>
                                                {{ $metricLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <input type="number" name="badges[{{ $key }}][threshold]" min="0" max="1000000"
                                           value="{{ old('badges.'.$key.'.threshold', $def['threshold'] ?? 0) }}"
                                           class="w-24 rounded-md border border-zinc-300 bg-white px-2 py-1 text-right text-sm dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="px-2 py-1 text-center">
                                    <input type="checkbox" name="badges_remove[{{ $key }}]" value="1"
                                           class="h-4 w-4 rounded border-zinc-400 text-red-600">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-700">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Adicionar conquista</p>
                <div class="grid gap-2 sm:grid-cols-5">
                    <input type="text" name="new_badge_key" placeholder="chave (a-z0-9_)" maxlength="50"
                           class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <input type="text" name="new_badge_label" placeholder="Nome" maxlength="100"
                           class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <input type="text" name="new_badge_icon" placeholder="🏅" maxlength="16"
                           class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <select name="new_badge_metric"
                            class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        @foreach ($metrics as $metric => $metricLabel)
                            <option value="{{ $metric }}">{{ $metricLabel }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="new_badge_threshold" placeholder="Limite" min="0" max="1000000"
                           class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Salvar configuração
            </button>
        </div>
    </form>

    {{-- Curve preview --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="mb-1 text-lg font-bold">Prévia da curva</h2>
        <p class="mb-3 text-sm text-zinc-500">Com a configuração atual salva ({{ $curves[$levelCurve] ?? $levelCurve }}, base {{ $levelBase }}).</p>

        <div class="mb-4 h-72 w-full">
            <canvas id="curveChart" aria-label="Gráfico da curva de níveis" role="img"></canvas>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-sm">
                <thead class="text-left text-xs uppercase text-zinc-500">
                    <tr><th class="px-2 py-1">Nível</th><th class="px-2 py-1">XP total</th><th class="px-2 py-1">XP para subir</th></tr>
                </thead>
                <tbody id="curveTableBody">
                    @foreach ($curvePreview as $row)
                        <tr class="border-t border-zinc-200 dark:border-zinc-800">
                            <td class="px-2 py-1 font-bold">{{ $row['level'] }}</td>
                            <td class="px-2 py-1">{{ number_format($row['total']) }}</td>
                            <td class="px-2 py-1 text-zinc-500">{{ number_format($row['needed']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Grant XP / badge --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"
         x-data="gamificationGrant({{ Illuminate\Support\Js::from($badges) }})">
        <h2 class="mb-1 text-lg font-bold">Dar XP / conquista</h2>
        <p class="mb-3 text-sm text-zinc-500">Busque um usuário e ajuste a experiência ou conceda/remova uma conquista.</p>

        <form method="POST" action="{{ route('admin.gamification.grant') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="user_id" x-model="userId">

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="relative">
                    <label class="block text-sm font-medium">Usuário</label>
                    <input type="text" x-model="query" @input.debounce.300ms="search()" placeholder="Nome ou e-mail"
                           class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                    <div x-show="results.length" x-cloak
                         class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-zinc-300 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <template x-for="user in results" :key="user.id">
                            <button type="button" @click="pick(user)"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                <span>
                                    <span class="font-medium" x-text="user.name"></span>
                                    <span class="text-zinc-500" x-text="' · ' + user.email"></span>
                                </span>
                                <span class="text-xs text-zinc-500" x-text="'Nv ' + user.level + ' · ' + user.experience + ' XP'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="selected" x-cloak class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                    <p class="font-semibold" x-text="selected ? selected.name : ''"></p>
                    <p class="text-zinc-500" x-text="selected ? ('Nível ' + selected.level + ' · ' + selected.experience + ' XP') : ''"></p>
                    <p class="mt-1 text-xs text-zinc-500" x-text="selected ? (selected.badges.length + ' conquista(s)') : ''"></p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-medium">XP (+ / −)</span>
                    <input type="number" name="exp" value="0" min="-1000000" max="1000000"
                           class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Conquista</span>
                    <select name="badge"
                            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option value="">— nenhuma —</option>
                        @foreach ($badges as $key => $def)
                            <option value="{{ $key }}">{{ $def['icon'] ?? '🏅' }} {{ $def['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Ação da conquista</span>
                    <select name="badge_action"
                            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        <option value="grant">Conceder</option>
                        <option value="revoke">Remover</option>
                    </select>
                </label>
            </div>

            <div class="flex justify-end">
                <button type="submit" x-bind:disabled="!userId"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-40">
                    Aplicar
                </button>
            </div>
        </form>
    </div>

    {{-- Backfill --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="mb-1 text-lg font-bold">Recalcular (retroativo)</h2>
        <p class="text-sm text-zinc-500">
            O site roda há anos: isto varre comics, posts, respostas, comentários, curtidas e coleções de cada usuário
            e soma o XP que falta. É idempotente (rodar de novo não duplica) e nunca tira XP.
        </p>
        <p class="mt-2 text-sm text-zinc-500">
            Mudou a curva ou a base de XP? Os níveis são gravados por usuário e ficam no valor antigo até ele ganhar XP de novo.
            Use <strong>Recalcular níveis</strong> para reprocessar todo mundo pela curva atual (o ranking e os selos passam a bater).
        </p>
        <div class="mt-3 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.gamification.backfill.preview') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    Prévia (sem alterar)
                </button>
            </form>
            <form method="POST" action="{{ route('admin.gamification.backfill') }}"
                  onsubmit="return confirm('Aplicar o recálculo retroativo agora?');">
                @csrf
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Aplicar recálculo
                </button>
            </form>
            <form method="POST" action="{{ route('admin.gamification.resync') }}"
                  onsubmit="return confirm('Recalcular o nível de todos os usuários pela curva atual?');">
                @csrf
                <button type="submit" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    Recalcular níveis
                </button>
            </form>
        </div>
    </div>

    {{-- Top users --}}
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="mb-3 text-lg font-bold">Top usuários</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-zinc-500">
                <tr><th class="px-2 py-1">#</th><th class="px-2 py-1">Usuário</th><th class="px-2 py-1">Nível</th><th class="px-2 py-1 text-right">XP</th></tr>
            </thead>
            <tbody>
                @forelse ($topUsers as $index => $user)
                    <tr class="border-t border-zinc-200 dark:border-zinc-800">
                        <td class="px-2 py-1 text-zinc-500">{{ $index + 1 }}</td>
                        <td class="px-2 py-1">{{ $user->name }}</td>
                        <td class="px-2 py-1">{{ (int) $user->level }}</td>
                        <td class="px-2 py-1 text-right font-medium">{{ number_format((int) $user->experience) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-2 py-6 text-center text-zinc-500">Sem dados ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function gamificationGrant(badges) {
        return {
            query: '',
            userId: '',
            results: [],
            selected: null,
            async search() {
                if (this.query.trim().length < 2) { this.results = []; return; }
                try {
                    const res = await fetch('{{ route('admin.gamification.users') }}?q=' + encodeURIComponent(this.query), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    this.results = data.data ?? [];
                } catch (e) {
                    this.results = [];
                }
            },
            pick(user) {
                this.selected = user;
                this.userId = user.id;
                this.query = user.name;
                this.results = [];
            },
        };
    }

    // ---- Level curve chart (Chart.js, loaded globally by the admin layout) ----
    (function () {
        const canvas = document.getElementById('curveChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const LEVELS = {{ count($curvePreview) }};
        const baseInput = document.querySelector('input[name="level_base"]');
        const curveSelect = document.querySelector('select[name="level_curve"]');
        const tableBody = document.getElementById('curveTableBody');

        // Mirrors App\Services\Gamification::thresholdFor().
        function threshold(level, base, curve) {
            if (level <= 1) return 0;
            const n = level - 1;
            switch (curve) {
                case 'linear': return base * n;
                case 'quadratic': return base * n * n;
                case 'exponential': return base * (Math.pow(2, Math.min(n, 40)) - 1);
                default: return Math.floor(base * n * (n + 1) / 2);
            }
        }

        function series() {
            const base = Math.max(1, parseInt(baseInput && baseInput.value, 10) || 100);
            const curve = (curveSelect && curveSelect.value) || 'triangular';
            const levels = [];
            const totals = [];
            const needed = [];
            for (let level = 1; level <= LEVELS; level++) {
                const total = threshold(level, base, curve);
                levels.push(level);
                totals.push(total);
                needed.push(total - threshold(level - 1, base, curve));
            }
            return { levels, totals, needed };
        }

        function fmt(n) {
            n = Number(n) || 0;
            if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1).replace('.', ',') + 'M';
            if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(1).replace('.', ',') + 'k';
            return n.toLocaleString('pt-BR');
        }

        const initial = series();
        const chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: initial.levels,
                datasets: [
                    {
                        type: 'line',
                        label: 'XP total (acumulado)',
                        data: initial.totals,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.15)',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 2,
                        fill: true,
                        yAxisID: 'y',
                    },
                    {
                        type: 'bar',
                        label: 'XP para subir',
                        data: initial.needed,
                        backgroundColor: 'rgba(16, 185, 129, 0.55)',
                        borderRadius: 3,
                        yAxisID: 'y1',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: '#9ca3af' } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('pt-BR') + ' XP',
                        },
                    },
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Nível', color: '#9ca3af' },
                        ticks: { color: '#9ca3af' },
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                    },
                    y: {
                        position: 'left',
                        title: { display: true, text: 'XP total', color: '#9ca3af' },
                        ticks: { color: '#9ca3af', callback: (v) => fmt(v) },
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                    },
                    y1: {
                        position: 'right',
                        title: { display: true, text: 'XP por nível', color: '#9ca3af' },
                        ticks: { color: '#9ca3af', callback: (v) => fmt(v) },
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });

        function refresh() {
            const data = series();
            chart.data.labels = data.levels;
            chart.data.datasets[0].data = data.totals;
            chart.data.datasets[1].data = data.needed;
            chart.update('none');

            if (tableBody) {
                tableBody.innerHTML = data.levels.map((level, i) =>
                    '<tr class="border-t border-zinc-200 dark:border-zinc-800">'
                    + '<td class="px-2 py-1 font-bold">' + level + '</td>'
                    + '<td class="px-2 py-1">' + data.totals[i].toLocaleString('pt-BR') + '</td>'
                    + '<td class="px-2 py-1 text-zinc-500">' + data.needed[i].toLocaleString('pt-BR') + '</td>'
                    + '</tr>'
                ).join('');
            }
        }

        if (baseInput) baseInput.addEventListener('input', refresh);
        if (curveSelect) curveSelect.addEventListener('change', refresh);
    })();
</script>
@endsection
