@extends('layouts.admin-layout')

@section('title', 'Banimentos do fórum')
@section('page-title', 'Banimentos do fórum')

@section('content')
    <div class="admin-card mb-6">
        <div class="admin-card-header"><span>Banir usuário</span></div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.forum.bans.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[16rem]">
                    <label class="admin-label" for="user_search">Usuário</label>
                    <input id="user_search" list="forum-users" autocomplete="off" class="admin-input w-full" placeholder="nome ou e-mail…">
                    <input type="hidden" name="user_id" id="user_id">
                    <datalist id="forum-users"></datalist>
                </div>
                <div>
                    <label class="admin-label" for="forum_category_id">Escopo</label>
                    <select id="forum_category_id" name="forum_category_id" class="admin-input">
                        <option value="">Fórum inteiro</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="admin-label" for="banned_until">Até (vazio = permanente)</label>
                    <input id="banned_until" type="datetime-local" name="banned_until" class="admin-input">
                </div>
                <div class="min-w-[14rem]">
                    <label class="admin-label" for="reason">Motivo</label>
                    <input id="reason" name="reason" maxlength="500" class="admin-input w-full">
                </div>
                <button class="admin-btn admin-btn-danger">Banir</button>
            </form>
            @error('user_id')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span>Banimentos ativos e históricos</span></div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Usuário</th><th>Escopo</th><th>Motivo</th><th>Até</th><th>Por</th><th></th></tr></thead>
                <tbody>
                    @forelse ($bans as $ban)
                        <tr>
                            <td>{{ $ban->user?->name ?? '—' }}</td>
                            <td>{{ $ban->category?->name ?? 'Fórum inteiro' }}</td>
                            <td class="max-w-xs break-words">{{ $ban->reason ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $ban->banned_until?->format('d/m/Y H:i') ?? 'Permanente' }}</td>
                            <td>{{ $ban->banner?->name ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.forum.bans.destroy', $ban->id) }}" class="inline" onsubmit="return confirm('Remover banimento?')">
                                    @csrf @method('DELETE')
                                    <button class="admin-btn admin-btn-sm admin-btn-secondary">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center opacity-60">Nenhum banimento.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $bans->links() }}</div>
@endsection

@section('scripts')
<script>
    (function () {
        const input = document.getElementById('user_search');
        const list = document.getElementById('forum-users');
        const hidden = document.getElementById('user_id');
        if (!input) return;
        let t = null;
        input.addEventListener('input', function () {
            hidden.value = '';
            clearTimeout(t);
            const q = input.value.trim();
            if (q.length < 2) return;
            t = setTimeout(function () {
                fetch('{{ route('admin.gamification.users') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        list.innerHTML = '';
                        (data.data || []).forEach(function (u) {
                            const opt = document.createElement('option');
                            opt.value = u.name + ' (' + u.email + ')';
                            opt.dataset.id = u.id;
                            list.appendChild(opt);
                        });
                    });
            }, 250);
        });
        input.addEventListener('change', function () {
            const match = Array.from(list.options).find(function (o) { return o.value === input.value; });
            if (match) hidden.value = match.dataset.id;
        });
    })();
</script>
@endsection
