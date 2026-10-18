@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl">
    <h2 class="text-xl font-semibold">Resultados para: <strong>{{ $searchTerm }}</strong></h2>
    @if($tagTerm)
        <div class="mb-4 mt-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-300">
                {{ $tagTerm }}
            </span>
        </div>
    @endif

    @if ($comics->count() > 0)
        <ul class="mt-4 grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($comics as $comic)
                <li>
                    <div class="mb-1 text-xs text-zinc-500">
                        Visualizações: {{ $comic->view_count }}
                    </div>
                    <x-comic-card :comic="$comic" :minified="false" :liked-comic-ids="$likedComicIds ?? []" />
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex justify-center">
            {{ $comics->links() }}
        </div>
    @else
        <p class="mt-4 rounded-xl border border-dashed border-zinc-800 px-4 py-10 text-center text-sm text-zinc-500">Nenhum comic encontrado.</p>
    @endif
</div>
@endsection
