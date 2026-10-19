@extends('layouts.app')

@section('title', 'Editar perfil')

@section('content')
<div class="mx-auto max-w-3xl">

    <h1 class="mb-6 text-2xl font-bold">Editar perfil</h1>

    <form action="{{ route('profile.update') }}"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">

        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-zinc-300">
                Nome
            </label>
            <input type="text"
                   id="name"
                   name="name"
                   value="{{ old('name', $user->name) }}"
                   required
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('name') border-red-500 @else border-zinc-700 @enderror
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">
                E-mail
            </label>
            <input type="email"
                   id="email"
                   name="email"
                   value="{{ old('email', $user->email) }}"
                   required
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('email') border-red-500 @else border-zinc-700 @enderror
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('email')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Avatar -->
        <div>
            <label for="avatar_image" class="mb-1 block text-sm font-medium text-zinc-300">
                Avatar
            </label>
            <div class="mb-2 flex items-center gap-3">
                <img id="avatarPreview"
                     src="{{ $user->avatar_image_path ? asset($user->avatar_image_path) : Vite::asset('resources/images/default-avatar.svg') }}"
                     alt="Prévia do avatar"
                     class="h-16 w-16 rounded-full border border-zinc-700 object-cover">
                <p class="text-xs text-zinc-500">Avatar atual — escolher um arquivo mostra a troca na hora.</p>
            </div>
            <input type="file"
                   id="avatar_image"
                   name="avatar_image"
                   accept="image/*"
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
            @error('avatar_image')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Banner -->
        <div>
            <label for="banner_image" class="mb-1 block text-sm font-medium text-zinc-300">
                Banner do perfil <span class="text-zinc-500">(imagem larga atrás do avatar e do nome)</span>
            </label>
            @if ($user->banner_image_path)
                <img id="bannerPreview" src="{{ asset($user->banner_image_path) }}"
                     alt="Banner atual"
                     class="mb-2 h-28 w-full rounded-lg border border-zinc-700 object-cover">
                <label class="mb-2 flex cursor-pointer items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox"
                           name="remove_banner"
                           value="1"
                           class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-red-600 focus:ring-red-500/40">
                    Remover banner atual
                </label>
            @endif
            <img id="bannerPreviewNew"
                     src=""
                     alt="Prévia do banner"
                     class="mb-2 hidden h-28 w-full rounded-lg border border-zinc-700 object-cover">
            <input type="file"
                   id="banner_image"
                   name="banner_image"
                   accept="image/*"
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
            <p class="mt-1 text-xs text-zinc-500">Paisagem funciona melhor (ex. 1200×400). Máx 4MB — enviar um novo substitui o atual.</p>
            @error('banner_image')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Bio -->
        <div>
            <label for="bio" class="mb-1 block text-sm font-medium text-zinc-300">
                Bio
            </label>
            <textarea id="bio"
                      name="bio"
                      rows="4"
                      class="w-full rounded-lg border px-3 py-2 text-sm
                             bg-zinc-950
                             @error('bio') border-red-500 @else border-zinc-700 @enderror
                             focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('bio', $user->bio) }}</textarea>
            @error('bio')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Links -->
        <div id="links-container">
            <label class="mb-2 block text-sm font-medium text-zinc-300">
                Links
            </label>

            <div id="links-list" class="space-y-2">
                @foreach (old('links', $user->links ?? []) as $link)
                    <div class="flex gap-2">
                        <input type="text"
                               name="links[]"
                               value="{{ $link }}"
                               class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
                        <button type="button"
                                class="remove-link rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">
                            Remover
                        </button>
                    </div>
                @endforeach
            </div>

            <button type="button"
                    id="add-link"
                    class="mt-3 inline-flex rounded-lg border border-zinc-700 px-3 py-2 text-sm hover:bg-zinc-800">
                Adicionar link
            </button>
        </div>

        <!-- Liked posts visibility -->
        <div class="flex items-start gap-3">
            <input type="checkbox"
                   id="show_liked_posts"
                   name="show_liked_posts"
                   value="1"
                   @checked(old('show_liked_posts', $user->show_liked_posts))
                   class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
            <div>
                <label for="show_liked_posts" class="block text-sm font-medium text-zinc-300">
                    Mostrar meus posts curtidos no perfil público
                </label>
                <p class="mt-0.5 text-xs text-zinc-500">
                    Desligado por padrão. Só você vê seus posts curtidos, a menos que marque isto.
                </p>
            </div>
        </div>

        <!-- Forum digest -->
        <div class="flex items-start gap-3">
            <input type="checkbox"
                   id="forum_digest"
                   name="forum_digest"
                   value="1"
                   @checked(old('forum_digest', $user->forum_digest))
                   class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
            <div>
                <label for="forum_digest" class="block text-sm font-medium text-zinc-300">
                    Receber resumo semanal do fórum por e-mail
                </label>
                <p class="mt-0.5 text-xs text-zinc-500">
                    Um e-mail por semana com os tópicos mais movimentados. Desligado por padrão.
                </p>
            </div>
        </div>

        <!-- Featured badges -->
        @if (! empty($badgeDefinitions) && $user->badges->isNotEmpty())
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">
                    Conquistas em destaque <span class="text-zinc-500">(até {{ $showcaseLimit }})</span>
                </label>
                <p class="mb-2 text-xs text-zinc-500">
                    Escolha quais aparecem no topo do seu perfil. Sem seleção, todas as conquistas são exibidas.
                </p>
                <div x-data="{ selected: @js(array_values(old('showcase_badges', $user->showcase_badges ?? []))), limit: {{ $showcaseLimit }} }"
                     class="flex flex-wrap gap-2">
                    @foreach ($user->badges as $badge)
                        @php($def = $badge->definition())
                        <label class="inline-flex cursor-pointer items-center gap-1 rounded-full border px-3 py-1 text-xs transition"
                               :class="selected.includes('{{ $badge->badge }}')
                                   ? 'border-indigo-500 bg-indigo-600/20 text-indigo-200'
                                   : 'border-zinc-700 bg-zinc-950 text-zinc-300 hover:border-zinc-500'">
                            <input type="checkbox"
                                   name="showcase_badges[]"
                                   value="{{ $badge->badge }}"
                                   class="sr-only"
                                   :checked="selected.includes('{{ $badge->badge }}')"
                                   @change="
                                       if ($event.target.checked) {
                                           if (selected.length >= limit) { $event.target.checked = false; return; }
                                           selected.push('{{ $badge->badge }}');
                                       } else {
                                           selected = selected.filter(k => k !== '{{ $badge->badge }}');
                                       }
                                   ">
                            <span>{{ $def['icon'] }}</span> {{ $def['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Password -->
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">
                Nova senha <span class="text-zinc-500">(deixe em branco para manter)</span>
            </label>
            <input type="password"
                   id="password"
                   name="password"
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('password') border-red-500 @else border-zinc-700 @enderror">
            @error('password')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password Confirmation -->
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-zinc-300">
                Confirmar nova senha
            </label>
            <input type="password"
                   id="password_confirmation"
                   name="password_confirmation"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('profile.show') }}"
               class="inline-flex justify-center rounded-lg border border-zinc-700 px-4 py-2 text-sm hover:bg-zinc-800">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Atualizar perfil
            </button>
        </div>

    </form>
</div>
@endsection
@push('scripts')
<script>
document.getElementById('add-link').addEventListener('click', function () {
    const linksContainer = document.getElementById('links-list');

    const wrapper = document.createElement('div');
    wrapper.className = 'flex gap-2';

    wrapper.innerHTML = `
        <input type="text"
               name="links[]"
               placeholder="Digite o link"
               class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        <button type="button"
                class="remove-link rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">
            Remover
        </button>
    `;

    linksContainer.appendChild(wrapper);
});

document.getElementById('links-container').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-link')) {
        e.target.parentElement.remove();
    }
});

// Instant client-side preview for avatar + banner uploads.
function livePreview(inputId, imgId) {
    var input = document.getElementById(inputId);
    var img = document.getElementById(imgId);
    if (!input || !img) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
}
livePreview('avatar_image', 'avatarPreview');
livePreview('banner_image', document.getElementById('bannerPreview') ? 'bannerPreview' : 'bannerPreviewNew');
</script>
@endpush
