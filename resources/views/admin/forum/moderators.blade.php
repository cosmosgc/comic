@extends('layouts.admin-layout')

@section('title', 'Moderadores do fórum')
@section('page-title', 'Moderadores do fórum')

@section('content')
    <div class="admin-card mb-6">
        <div class="admin-card-header"><span>Conceder moderação</span></div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.forum.moderators.store') }}" class="flex flex-wrap items-end gap-3">
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
                <button type="submit" class="admin-btn admin-btn-primary">Adicionar</button>
            </form>
            @error('user_id')<p class="mt-2 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span>Moderadores atuais</span></div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Usuário</th><th>Escopo</th><th>Concedido por</th><th>Quando</th><th></th></tr></thead>
                <tbody>
                    @forelse ($moderators as $moderator)
                        <tr>
                            <td>{{ $moderator->user?->name ?? '—' }}</td>
                            <td>{{ $moderator->category?->name ?? 'Fórum inteiro' }}</td>
                            <td>{{ $moderator->granter?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $moderator->created_at->diffForHumans() }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.forum.moderators.destroy', $moderator->id) }}" class="inline" onsubmit="return confirm('Revogar moderação?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Revogar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center opacity-60">Nenhum moderador além dos admins.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
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
