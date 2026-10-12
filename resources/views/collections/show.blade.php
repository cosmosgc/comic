@extends('layouts.app')

@section('title', $collection->name)

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-700 bg-green-900/40 px-4 py-3 text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Collection header -->
    <div class="mb-8 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        @php($cover = $collection->coverComic())
        @if ($cover && $cover->image_path)
            <img src="{{ asset('storage/' . $cover->image_path) }}"
                 alt="Capa de {{ $cover->title }}"
                 class="mb-4 aspect-[3/4] w-32 rounded-lg border border-zinc-700 object-cover">
        @endif
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($collection->is_favorites ?? false)
                <span class="rounded-full border border-amber-600/40 bg-amber-600/10 px-2 py-0.5 font-semibold text-amber-400" title="Favoritos automáticos">
                    ★ Favoritos
                </span>
            @endif
            @if (! ($collection->is_public ?? true))
                <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-zinc-400">privada</span>
            @endif
            <span class="text-zinc-500">
                {{ $comics->total() }} comics
                @if ($collection->user)
                    · por <a href="{{ route('profile.public.show.username', ['username' => $collection->user->name]) }}"
                            class="font-semibold text-zinc-300 hover:underline">{{ $collection->user->name }}</a>
                @endif
            </span>
        </div>

        <h1 class="mt-2 text-2xl font-bold">{{ $collection->name }}</h1>

        @if ($collection->description)
            <p class="mt-2 max-w-2xl text-sm text-zinc-400">{{ $collection->description }}</p>
        @endif

        @if ($canEdit ?? false)
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('collections.edit', $collection) }}"
                   class="inline-flex rounded-lg border border-yellow-600 px-3 py-2 text-sm font-medium text-yellow-400 hover:bg-yellow-600/10">
                    Editar coleção
                </a>
                <form method="POST" action="{{ route('collections.destroy', $collection) }}"
                      onsubmit="return confirm('Excluir esta coleção? Os comics continuam intactos.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex rounded-lg border border-red-600 px-3 py-2 text-sm font-medium text-red-400 hover:bg-red-600/10">
                        Excluir
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Search within the collection -->
    <form method="GET" action="{{ route('collections.show', $collection) }}" class="mb-6 flex max-w-xl gap-2">
        <input type="text"
               name="q"
               value="{{ $searchTerm }}"
               placeholder="Buscar nesta coleção por título ou autor…"
               autocomplete="off"
               class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm
                      focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
        <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
            Buscar
        </button>
        @if ($searchTerm !== '')
            <a href="{{ route('collections.show', $collection) }}"
               class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 transition hover:bg-zinc-900">
                Limpar
            </a>
        @endif
    </form>

    <!-- Comics grid -->
    @if ($comics->count())
        <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($comics as $comic)
                <x-comic-card :comic="$comic" :liked-comic-ids="$likedComicIds ?? []" />
            @endforeach
        </div>

        <div class="mt-8 flex justify-center">
            {{ $comics->links() }}
        </div>
    @else
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 text-center text-zinc-500">
            @if ($searchTerm !== '')
                Nada para "{{ $searchTerm }}" nesta coleção.
            @else
                Sem comics nesta coleção ainda.
            @endif
        </div>
    @endif

</div>
@endsection
