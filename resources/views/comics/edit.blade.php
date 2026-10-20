@extends('layouts.app')

@section('title', 'Editar comic')

@section('content')
<div class="mx-auto max-w-5xl">

    <h1 class="mb-6 text-2xl font-bold">
        Editar comic: {{ $comic->title }}
    </h1>

    @if (session('success'))
        <div class="mb-6 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Edit comic form -->
    <form action="{{ route('comics.update', $comic->id) }}"
          method="POST"
          enctype="multipart/form-data"
          class="mb-10 space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        @csrf
        @method('PUT')

        <!-- Title -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Título
            </label>
            <input type="text"
                   id="title"
                   name="title"
                   value="{{ old('title', $comic->title) }}"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('title')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Author / artist -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Autor / artista
            </label>
            <input type="text"
                   id="author"
                   name="author"
                   value="{{ old('author', $comic->author) }}"
                   placeholder="Nome do autor ou artista"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('author')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Descrição
            </label>
            <textarea name="description"
                      rows="4"
                      class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                             focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('description', $comic->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Tags -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Tags
            </label>
            @include('components.tag-editor', [
                'name' => 'tags',
                'value' => old('tags', $comic->tags->pluck('name')->implode(', ')),
                'placeholder' => 'Digite para buscar ou criar tags…',
                'styles' => $comic->tags->mapWithKeys(fn ($tag) => [
                    mb_strtolower($tag->name) => $tag->displayCategory?->styleArray(),
                ])->filter()->all(),
            ])
            @error('tags')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- External tag lookup (E-Hentai API): fetches web tags and
             auto-checks the ones matching local tags. -->
        <div>
            @include('components.tag-lookup', ['tagsInputId' => 'tags', 'titleInputId' => 'title', 'authorInputId' => 'author'])
        </div>

        <!-- Schedule publishing -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Agendar publicação
            </label>
            <input type="datetime-local"
                   id="published_at"
                   name="published_at"
                   value="{{ old('published_at', $comic->published_at?->format('Y-m-d\\TH:i')) }}"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @include('components.scheduled-date-calendar', [
                'inputId' => 'published_at',
                'scheduledDateCounts' => $scheduledDateCounts,
            ])
            @if ($comic->isScheduled())
                <p class="mt-1 text-xs text-amber-300">
                    ⏳ Agendado para {{ $comic->published_at->format('d/m/Y H:i') }} — visível só para você e admins até lá.
                </p>
            @else
                <p class="mt-1 text-xs text-zinc-500">
                    Vazio = publicado. Escolha uma data futura para agendar; limpar o campo publica na hora.
                </p>
            @endif
            @error('published_at')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Members-only flag: guests cannot open it and see a locked card -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4">
            <label class="flex items-start gap-3">
                <input type="hidden" name="is_exclusive" value="0">
                <input type="checkbox"
                       name="is_exclusive"
                       value="1"
                       @checked(old('is_exclusive', $comic->isExclusive()))
                       class="mt-0.5 h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-fuchsia-500 focus:ring-fuchsia-500/40">
                <span>
                    <span class="block text-sm font-medium text-zinc-200">🔒 Exclusivo para membros</span>
                    <span class="mt-1 block text-xs text-zinc-500">
                        Visitantes deslogados não abrem o comic (a página dá 404); nas listas ele fica como card
                        bloqueado, com o convite para entrar. Membros logados leem normalmente.
                    </span>
                </span>
            </label>
            @error('is_exclusive')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            Salvar alterações
        </button>
    </form>

    <!-- Reorder pages -->
    <div class="mb-10">
        <h2 class="mb-4 text-xl font-semibold">
            Reordenar páginas
        </h2>

        <ul id="sortable"
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">

            @foreach($comic->pages as $page)
                <li data-id="{{ $page->id }}"
                    class="group relative flex flex-col items-center rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow">

                    <!-- Cover badge -->
                    @if($comic->image_path === $page->image_path)
                        <span class="absolute left-2 top-2 rounded-full bg-emerald-600 px-2 py-1 text-xs font-semibold text-white">
                            Capa
                        </span>
                    @endif

                    <img src="{{ asset('storage/' . $page->image_path) }}"
                        alt="Página {{ $page->page_number }}"
                        class="mb-2 w-full rounded-lg object-cover">

                    <span class="mb-2 text-sm text-zinc-300">
                        Página {{ $page->page_number }}
                    </span>

                    <div class="flex gap-2">
                        <button type="button"
                                data-page-id="{{ $page->id }}"
                                class="set-cover rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white
                                    hover:bg-indigo-500">
                            Definir capa
                        </button>

                        <button type="button"
                                data-id="{{ $page->id }}"
                                class="delete-page rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white
                                    hover:bg-red-500">
                            Excluir
                        </button>
                    </div>
                </li>

            @endforeach

        </ul>
    </div>

    <!-- Add new page -->
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        <h2 class="mb-4 text-xl font-semibold">
            Adicionar nova página
        </h2>

        <form action="{{ route('pages.addPage', $comic->id) }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">
                    Enviar imagem da página
                </label>
                <input type="file"
                       id="image"
                       name="image"
                       required
                       accept="image/*"
                       class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                              file:mr-4 file:rounded-md file:border-0
                              file:bg-indigo-600 file:px-4 file:py-2
                              file:text-sm file:font-medium file:text-white
                              hover:file:bg-indigo-500">
                <p class="mt-1 text-xs text-zinc-500">
                    Formatos aceitos: JPG, JPEG, PNG. Tamanho máximo: 10MB.
                </p>
            </div>

            <button type="submit"
                    class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white
                           hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                Enviar página
            </button>
        </form>
    </div>

</div>
@endsection


@push('scripts')
<script type="module">
    // Sortable is bundled by resources/js/app.js (deferred module, runs first).
    const deletePageBaseUrl = "{{ url('page') }}";

    // Initialize Sortable.js on the pages list
    var sortable = new Sortable(document.getElementById('sortable'), {
        animation: 150,
        onEnd: function (/**Event*/evt) {
            // Get the reordered page IDs
            let orderedPages = [];
            document.querySelectorAll('#sortable li').forEach(function (li, index) {
                orderedPages.push({
                    id: li.getAttribute('data-id'),
                    page_number: index + 1 // Set new page number
                });
            });

            // Send the reordered data to the server
            fetch('{{ route('comics.reorderPages', $comic->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(orderedPages)
            });
        }
    });

    // Delete page functionality
    document.querySelectorAll('.delete-page').forEach(function (button) {
        button.addEventListener('click', function () {
            let pageId = this.getAttribute('data-id');
            console.log('{{ route('pages.deletePage', $comic->id) }}');
            fetch(`${deletePageBaseUrl}/${pageId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the page from the DOM
                    this.closest('li').remove();
                }
            });
        });
    });
</script>
<script>
document.querySelectorAll('.set-cover').forEach(button => {
    button.addEventListener('click', function () {
        const pageId = this.getAttribute('data-page-id');

        fetch('{{ route('comics.setCover', $comic->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ page_id: pageId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Optional: visual feedback
                document.querySelectorAll('[data-page-id]').forEach(btn => {
                    btn.closest('li').querySelector('.absolute')?.remove();
                });

                const badge = document.createElement('span');
                badge.className = 'absolute left-2 top-2 rounded-full bg-emerald-600 px-2 py-1 text-xs font-semibold text-white';
                badge.innerText = 'Cover';

                this.closest('li').appendChild(badge);
            }
        });
    });
});
</script>

@endpush
