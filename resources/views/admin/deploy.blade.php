@extends('layouts.admin-layout')

@section('title', 'Deploy')
@section('page-title', 'Deploy FTP')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif

    <p class="admin-muted mb-6 max-w-3xl">
        Envia o código do projeto para a hospedagem via FTPS — sem console no host.
        Rode a partir da sua cópia local. A atualização <strong>se recusa a rodar</strong> a menos que o
        diretório remoto seja este projeto Laravel (veja as verificações abaixo). <code class="admin-code">.env</code>, uploads e
        caches nunca são sobrescritos, e nada é apagado no remoto.
    </p>

    {{-- Configuration --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Configuração FTP (.env)</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <tbody>
                    <tr>
                        <td>Extensão cURL do PHP</td>
                        <td>
                            @if ($curlAvailable)
                                <span class="admin-badge admin-badge-success">Disponível</span>
                            @else
                                <span class="admin-badge admin-badge-danger">Faltando — deploy FTP exige ext-curl</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td>FTP_HOST</td><td><code>{{ $host ?: '— não definido —' }}</code></td></tr>
                    <tr><td>FTP_PORT</td><td><code>{{ $port }}</code> {{ $ssl ? '(FTPS)' : '(FTP simples)' }}</td></tr>
                    <tr><td>FTP_USERNAME</td><td><code>{{ $username ?: '— não definido —' }}</code></td></tr>
                    <tr><td>FTP_PASSWORD</td><td>{{ $configured || !in_array('FTP_PASSWORD', $missing) ? '•••••••• (definida)' : '— não definida —' }}</td></tr>
                    <tr><td>FTP_ROOT (pasta do projeto)</td><td><code>{{ $root }}</code></td></tr>
                    <tr><td>FTP_PUBLIC_DIR (raiz web)</td><td><code>{{ $publicDir }}</code></td></tr>
                </tbody>
            </table>
        </div>
        @if (!$configured)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm text-red-700 dark:border-zinc-800 dark:text-red-300">
                Faltando: <code class="admin-code">{{ implode(', ', $missing) }}</code>. Defina no <code class="admin-code">.env</code> (veja <code class="admin-code">.env.example</code>).
            </div>
        @endif
    </div>

    {{-- Verify --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>1. Verificar diretório remoto</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.deploy.verify') }}">
                @csrf
                <button type="submit" class="admin-btn admin-btn-secondary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                    Testar conexão e verificar projeto
                </button>
            </form>

            @if ($verification)
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <tbody>
                            @foreach ($verification['checks'] as $check)
                                <tr>
                                    <td>{{ $check['label'] }}</td>
                                    <td>
                                        @if ($check['ok'])
                                            <span class="admin-badge admin-badge-success">PASS</span>
                                        @else
                                            <span class="admin-badge admin-badge-danger">FAIL</span>
                                        @endif
                                    </td>
                                    <td class="admin-muted">{{ $check['detail'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (!empty($verification['root_entries']))
                    <div class="mt-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                        <strong>Conteúdo do diretório remoto ({{ count($verification['root_entries']) }}):</strong><br>
                        <code class="admin-code">{{ implode(', ', $verification['root_entries']) }}</code>
                        <br><span class="admin-muted">Use esta lista para ajustar FTP_ROOT / FTP_PUBLIC_DIR.</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Run --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>2. Rodar atualização</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.deploy.run') }}"
                  onsubmit="return confirm('Enviar arquivos alterados para o host?')" class="space-y-2.5">
                @csrf
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="dry_run" id="dry_run" value="1" checked>
                    <span>Simulação (mostra o que seria enviado)</span>
                </label>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="force" id="force" value="1">
                    <span>Forçar reenvio de todos os arquivos</span>
                </label>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="include_vendor">
                        <input type="checkbox" class="admin-check mt-0.5" name="include_vendor" id="include_vendor" value="1">
                        <span>Incluir vendor/ (~9.000 arquivos, ~49 MB)</span>
                    </label>
                    <span class="admin-help ml-6">Ignorado por padrão — vendor raramente muda. Se o host ainda não tem vendor/, ele entra automaticamente.</span>
                </div>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="quick">
                        <input type="checkbox" class="admin-check mt-0.5" name="quick" id="quick" value="1" checked>
                        <span>Sincronização rápida (só alterados)</span>
                    </label>
                    <span class="admin-help ml-6">
                        @if ($quickSince)
                            Desde o último sucesso ({{ date('d/m/Y H:i', $quickSince) }}) — termina em cerca de um minuto.
                        @else
                            Sem sucesso anterior — a primeira vez é sempre completa.
                        @endif
                        Edições feitas no remoto fora dos deploys só aparecem na varredura completa.
                    </span>
                </div>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="full" id="full" value="1">
                    <span>Varredura completa (ignora a rápida)</span>
                </label>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="include_build">
                        <input type="checkbox" class="admin-check mt-0.5" name="include_build" id="include_build" value="1" checked>
                        <span>Incluir assets compilados (public/build/)</span>
                    </label>
                    <span class="admin-help ml-6">Desmarque para mudanças só em PHP — pula o build do Vite.</span>
                </div>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="confirm" id="confirm" value="1" required>
                    <span>Entendo que isto envia código ao host ativo</span>
                </label>
                <div class="pt-1">
                    <button type="submit" class="admin-btn admin-btn-primary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                        Rodar atualização FTP
                    </button>
                </div>
            </form>
            <p class="admin-muted mt-3">
                Após o envio, abra <a href="{{ route('admin.migrations') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Migrações</a> no host
                e rode as migrações pendentes.
            </p>
        </div>
    </div>

    {{-- Finished run (persists across reloads, no polling) --}}
    @if ($runId && $runStatus && in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled']))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Última execução</span>
                @if (($runStatus['state'] ?? null) === 'done')
                    <span class="admin-badge admin-badge-success">concluída</span>
                @else
                    <span class="admin-badge admin-badge-danger">{{ ['failed' => 'falhou', 'cancelled' => 'cancelada'][$runStatus['state'] ?? ''] ?? ($runStatus['state'] ?? '') }}</span>
                @endif
            </div>
            <div class="admin-card-body space-y-2">
                @if (!empty($runStatus['message']))
                    <p>{{ $runStatus['message'] }}</p>
                @endif
                @if (isset($runStatus['done'], $runStatus['total']) && $runStatus['total'])
                    <p class="admin-muted">Enviados {{ $runStatus['done'] }} de {{ $runStatus['total'] }} arquivos.</p>
                @endif
                @if (!empty($runStatus['log']))
                    <pre id="deploy-result-log" class="admin-log">{{ implode("\n", array_slice($runStatus['log'], -80)) }}</pre>
                @endif
            </div>
        </div>
    @endif

    {{-- Live progress (background run) --}}
    @if ($runId && (! $runStatus || ! in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled'])))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Progresso <span class="admin-muted font-normal">(ao vivo — não precisa manter a página aberta)</span></span>
                <form method="POST" action="{{ route('admin.deploy.cancel', ['id' => $runId]) }}"
                      onsubmit="return confirm('Cancelar esta execução? Arquivos já enviados ficam no lugar.')">
                    @csrf
                    <button type="submit" id="deploy-cancel" class="admin-btn admin-btn-sm border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10">Cancelar execução</button>
                </form>
            </div>
            <div class="admin-card-body">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span id="deploy-phase" class="admin-badge admin-badge-secondary">queued</span>
                    <span id="deploy-modes" class="admin-muted"></span>
                </div>
                <div class="mb-4 h-6 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <div id="deploy-progress" class="flex h-full items-center justify-center text-xs font-semibold text-white transition-all duration-500"
                         role="progressbar" style="width: 0%;">0%</div>
                </div>
                <div class="mb-4 grid grid-cols-3 gap-2 text-center sm:grid-cols-6">
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Enviados</div>
                        <div id="stat-sent" class="text-lg font-bold">0</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Total</div>
                        <div id="stat-total" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Restantes</div>
                        <div id="stat-remaining" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Tempo</div>
                        <div id="stat-elapsed" class="text-lg font-bold">0s</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Taxa</div>
                        <div id="stat-rate" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Falta</div>
                        <div id="stat-eta" class="text-lg font-bold">—</div>
                    </div>
                </div>
                <p class="admin-muted mb-1 text-xs">Enviando agora</p>
                <p id="deploy-current" class="mb-2"><code class="admin-code">—</code></p>
                <p id="deploy-counts" class="admin-muted mb-2 text-xs"></p>
                <p id="deploy-message" class="admin-muted mb-2">Starting…</p>
                <pre id="deploy-log" class="admin-log"></pre>
            </div>
        </div>
        <script>
            (function () {
                const url = "{{ route('admin.deploy.status', ['id' => $runId]) }}";
                const bar = document.getElementById('deploy-progress');
                const msg = document.getElementById('deploy-message');
                const log = document.getElementById('deploy-log');
                const phaseEl = document.getElementById('deploy-phase');
                const modesEl = document.getElementById('deploy-modes');
                const currentEl = document.getElementById('deploy-current');
                const countsEl = document.getElementById('deploy-counts');
                const statSent = document.getElementById('stat-sent');
                const statTotal = document.getElementById('stat-total');
                const statRemaining = document.getElementById('stat-remaining');
                const statElapsed = document.getElementById('stat-elapsed');
                const statRate = document.getElementById('stat-rate');
                const statEta = document.getElementById('stat-eta');
                const startMs = Date.now();

                function fmtDuration(sec) {
                    sec = Math.max(0, Math.floor(sec));
                    if (sec < 60) return sec + 's';
                    const m = Math.floor(sec / 60);
                    if (m < 60) return m + 'm ' + (sec % 60) + 's';
                    return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
                }

                const phaseLabels = {
                    queued: ['na fila', 'admin-badge-secondary'],
                    listing: ['listando arquivos', 'admin-badge-info'],
                    uploading: ['enviando', 'admin-badge-primary'],
                    done: ['concluída', 'admin-badge-success'],
                    failed: ['falhou', 'admin-badge-danger'],
                    cancelling: ['cancelando', 'admin-badge-warning'],
                    cancelled: ['cancelada', 'admin-badge-warning'],
                };

                const timer = setInterval(async () => {
                    let res;
                    try {
                        res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                    } catch (e) {
                        msg.textContent = 'Perdemos contato com o worker — recarregue a página para verificar.';
                        clearInterval(timer);
                        return;
                    }
                    const s = await res.json();
                    const total = s.total || 0;
                    const done = s.done || 0;
                    const remaining = total > 0 ? Math.max(0, total - done) : null;
                    const pct = total > 0 ? Math.round((done / total) * 100) : (s.state === 'done' ? 100 : 5);
                    bar.style.width = pct + '%';
                    bar.innerText = pct + '%';

                    statSent.textContent = done;
                    statTotal.textContent = total > 0 ? total : '—';
                    statRemaining.textContent = remaining === null ? '—' : remaining;

                    const elapsedSec = (Date.now() - startMs) / 1000;
                    statElapsed.textContent = fmtDuration(elapsedSec);
                    if (elapsedSec > 5 && done > 0 && (s.state === 'running')) {
                        const perSec = done / elapsedSec;
                        statRate.textContent = perSec >= 1
                            ? perSec.toFixed(1) + '/s'
                            : (perSec * 60).toFixed(1) + '/min';
                        statEta.textContent = remaining !== null && perSec > 0
                            ? fmtDuration(remaining / perSec)
                            : '—';
                    } else if (['done', 'failed', 'cancelled'].includes(s.state)) {
                        statRate.textContent = '—';
                        statEta.textContent = '0s';
                    }

                    const phase = s.phase || s.state || 'queued';
                    const [label, cls] = phaseLabels[phase] || [phase, 'admin-badge-secondary'];
                    phaseEl.textContent = label;
                    phaseEl.className = 'admin-badge ' + cls;

                    const modes = [];
                    if (s.dry_run) modes.push('simulação');
                    if (s.force) modes.push('forçado');
                    if (s.include_vendor) modes.push('com vendor');
                    if (s.quick) modes.push('rápido');
                    if (s.full) modes.push('completo');
                    if (s.include_build === false) modes.push('sem build');
                    modesEl.textContent = modes.join(' • ');

                    currentEl.innerHTML = s.current ? '<code class="admin-code">' + s.current.replace(/</g, '&lt;') + '</code>' : '<code class="admin-code">—</code>';

                    const c = s.counts || {};
                    const parts = [];
                    if (c.skipped) parts.push(c.skipped + ' iguais');
                    if (c.excluded) parts.push(c.excluded + ' excluídos');
                    if (c.vendor_skipped) parts.push(c.vendor_skipped + ' vendor pulados');
                    if (c.quick_skipped) parts.push(c.quick_skipped + ' mais antigos que o último sucesso');
                    if (c.build_skipped) parts.push(c.build_skipped + ' build pulado');
                    if (c.quick) parts.push('modo rápido');
                    countsEl.textContent = parts.join(' • ');

                    if (s.message) msg.textContent = s.message;
                    else if (s.state === 'running') msg.textContent = s.dry_run ? 'Simulação em andamento…' : 'Enviando…';
                    else if (s.state === 'queued') msg.textContent = 'Worker iniciando…';
                    const lines = s.log || (s.log_tail ? [s.log_tail] : []);
                    if (lines.length) log.textContent = lines.slice(-80).join("\n");
                    const cancelBtn = document.getElementById('deploy-cancel');
                    if (s.state === 'done' || s.state === 'failed' || s.state === 'cancelled') {
                        clearInterval(timer);
                        bar.classList.add(s.state === 'done' ? 'is-done' : 'is-failed');
                        if (cancelBtn) cancelBtn.disabled = true;
                        if (s.state === 'cancelled' && s.message) msg.textContent = s.message + ' Rode de novo para continuar.';
                    } else if (s.state === 'cancelling') {
                        if (cancelBtn) cancelBtn.disabled = true;
                    }
                }, 2000);
            })();
        </script>
    @endif
@endsection
