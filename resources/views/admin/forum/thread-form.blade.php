@extends('layouts.admin-layout')

@section('title', 'Editar tópico')
@section('page-title', 'Editar tópico')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('admin.forum.threads') }}" class="admin-btn admin-btn-sm admin-btn-secondary">← Tópicos</a>
        <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}"
           target="_blank" rel="noopener" class="admin-btn admin-btn-sm admin-btn-secondary">Ver no fórum ↗</a>
        <span class="opacity-60">
            por {{ $thread->author?->name ?? '—' }} · {{ $thread->created_at->format('d/m/Y H:i') }} · {{ $thread->reply_count }} resposta(s)
        </span>
    </div>

    <form method="POST"
          action="{{ route('admin.forum.threads.update', $thread->id) }}"
          enctype="multipart/form-data"
          class="admin-card max-w-2xl">
        @csrf
        @method('PUT')

        <div class="admin-card-body space-y-4">
            <div>
                <label class="admin-label" for="title">Título</label>
                <input id="title" name="title" value="{{ old('title', $thread->title) }}" required minlength="3" maxlength="150" class="admin-input w-full">
                @error('title')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="admin-label" for="forum_category_id">Quadro</label>
                    <select id="forum_category_id" name="forum_category_id" class="admin-input w-full">
                        @foreach ($boards as $board)
                            <option value="{{ $board->id }}" @selected((int) old('forum_category_id', $thread->forum_category_id) === $board->id)>{{ $board->name }}</option>
                        @endforeach
                    </select>
                    @error('forum_category_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="admin-label" for="type">Tipo</label>
                    @if ($thread->isPoll())
                        <p class="py-2 text-sm opacity-70">Enquete — o tipo não pode ser alterado por aqui.</p>
                    @else
                        <select id="type" name="type" class="admin-input w-full">
                            <option value="discussion" @selected(old('type', $thread->type) === 'discussion')>Discussão</option>
                            <option value="question" @selected(old('type', $thread->type) === 'question')>Pergunta</option>
                        </select>
                        @error('type')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    @endif
                </div>
            </div>

            <div>
                <label class="admin-label" for="banner">Banner do tópico</label>
                <img id="thread-banner-preview"
                     src="{{ $thread->bannerUrl() ?? '' }}"
                     alt="Prévia do banner do tópico"
                     @class(['mb-2 h-24 w-full max-w-sm rounded-lg border border-zinc-800 object-cover', 'hidden' => ! $thread->banner_image_path])
                     style="object-position: {{ $thread->bannerPosition() }};">
                @if ($thread->banner_image_path)
                    <label class="mb-2 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remove_banner" value="1"> Remover banner atual
                    </label>
                @endif
                <input id="banner" type="file" name="banner" accept="image/*" class="admin-input w-full">
                <x-banner-crop preview-id="thread-banner-preview"
                               :x="old('banner_position_x', $thread->banner_position_x ?? 50)"
                               :y="old('banner_position_y', $thread->banner_position_y ?? 50)" />
                @error('banner')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $thread->is_pinned))> Fixado
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_locked" value="1" @checked(old('is_locked', $thread->is_locked))> Trancado
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_approved" value="1" @checked(old('is_approved', $thread->is_approved))> Aprovado
                </label>
            </div>

            <p class="text-xs opacity-60">Slug atual: {{ $thread->slug }}. Ele é recalculado quando o título ou o quadro mudam.</p>
        </div>

        <div class="admin-card-body flex items-center gap-3 border-t border-zinc-200 dark:border-zinc-800">
            <button type="submit" class="admin-btn admin-btn-primary">Salvar</button>
            <a href="{{ route('admin.forum.threads') }}" class="admin-btn admin-btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('banner');
        var preview = document.getElementById('thread-banner-preview');
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    });
</script>
@endpush
