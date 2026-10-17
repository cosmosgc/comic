@extends('layouts.admin-layout')

@section('title', 'Configurações do fórum')
@section('page-title', 'Configurações do fórum')

@section('content')
    <form method="POST" action="{{ route('admin.forum.settings.save') }}" class="admin-card max-w-3xl">
        @csrf
        <div class="admin-card-body space-y-4">
            <label class="admin-check">
                <input type="checkbox" name="enabled" value="1" @checked($enabled)> Fórum ativo
                @if (! empty($overridden['enabled']))<span class="admin-badge admin-badge-secondary">override</span>@endif
            </label>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="admin-label" for="approval_mode">Aprovação de posts</label>
                    <select id="approval_mode" name="approval_mode" class="admin-input w-full">
                        @foreach (['none' => 'Nenhuma', 'first_post' => 'Primeiro post', 'all' => 'Todos'] as $key => $label)
                            <option value="{{ $key }}" @selected($approvalMode === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="admin-label" for="edit_grace_minutes">Janela de edição (min)</label>
                    <input id="edit_grace_minutes" type="number" name="edit_grace_minutes" min="0" max="10080" value="{{ $editGraceMinutes }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="min_account_age_hours">Idade mínima da conta (h)</label>
                    <input id="min_account_age_hours" type="number" name="min_account_age_hours" min="0" max="8760" value="{{ $minAccountAgeHours }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="max_attachments">Máx. anexos por post</label>
                    <input id="max_attachments" type="number" name="max_attachments" min="0" max="20" value="{{ $maxAttachments }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="posts_per_page">Posts por página</label>
                    <input id="posts_per_page" type="number" name="posts_per_page" min="5" max="100" value="{{ $postsPerPage }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="hot_window_days">Janela "em alta" (dias)</label>
                    <input id="hot_window_days" type="number" name="hot_window_days" min="1" max="365" value="{{ $hotWindowDays }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="auto_lock_days">Trancar inativos (dias, 0 = nunca)</label>
                    <input id="auto_lock_days" type="number" name="auto_lock_days" min="0" max="3650" value="{{ $autoLockDays }}" class="admin-input w-full">
                </div>
            </div>

            <div>
                <label class="admin-label" for="allowed_mime">MIME permitidos (separados por vírgula)</label>
                <input id="allowed_mime" name="allowed_mime" value="{{ $allowedMime }}" class="admin-input w-full">
            </div>

            <div>
                <label class="admin-label" for="prohibited_words">Palavras proibidas (separadas por vírgula)</label>
                <textarea id="prohibited_words" name="prohibited_words" rows="2" class="admin-input w-full">{{ $prohibitedWords }}</textarea>
            </div>
        </div>

        <div class="admin-card-body border-t border-zinc-200 dark:border-zinc-800">
            <button type="submit" class="admin-btn admin-btn-primary">Salvar configurações</button>
        </div>
    </form>
@endsection
