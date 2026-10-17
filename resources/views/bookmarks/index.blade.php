@extends('layouts.app')

@section('title', 'Salvos')

@section('content')
<div class="mx-auto max-w-xl border-x border-zinc-800 min-h-screen">

    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 px-4 py-3 backdrop-blur">
        <h2 class="text-xl font-bold">Salvos</h2>
        <p class="text-sm text-zinc-500">Só você vê os posts que salvou.</p>
    </div>

    @if ($posts === null)
        <div class="p-8 text-center text-zinc-500">
            Os salvos ainda não estão disponíveis neste servidor.
        </div>
    @else
        @forelse ($posts as $post)
            @include('posts.post', ['post' => $post, 'likedPostIds' => $likedPostIds ?? [], 'bookmarkedPostIds' => $bookmarkedPostIds ?? []])
        @empty
            <div class="p-8 text-center text-zinc-500">
                Você ainda não salvou nenhum post. Use o ícone de marcador para guardar algo para depois.
            </div>
        @endforelse

        @if ($posts->hasPages())
            <div class="flex justify-center border-t border-zinc-800 p-4">
                {{ $posts->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
