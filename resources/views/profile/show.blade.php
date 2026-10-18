@extends('layouts.app')

@section('title', 'Seu perfil')

@section('content')
<div class="mx-auto max-w-7xl">

    <!-- Page title -->
    <h1 class="mb-6 text-2xl font-bold">Seu perfil</h1>

    <!-- Success message -->
    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-700 bg-green-900/40 px-4 py-3 text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Profile card -->
    @include('profile.card', [
        'user' => $user,
        'showEdit' => true,
        'followsTab' => $followsTab ?? false,
        'followersCount' => $followersCount ?? 0,
        'followingCount' => $followingCount ?? 0,
        'canFollow' => false,
    ])

    @php
        $likesAvailable = $likedPosts !== null;
        $collectionsAvailable = $collections !== null;
        $requestedTab = request()->input('tab');
        $activeTab = ($requestedTab === 'likes' && $likesAvailable) || ($requestedTab === 'collections' && $collectionsAvailable)
            ? $requestedTab
            : 'comics';
    @endphp
    @include('profile.tabs', ['activeTab' => $activeTab, 'likesTab' => $likesTab ?? true, 'collectionsTab' => $collectionsTab ?? false, 'followsTab' => $followsTab ?? false, 'followersCount' => $followersCount ?? 0, 'followingCount' => $followingCount ?? 0])

    @if ($activeTab === 'likes')
        <!-- Liked posts (always visible to the owner) -->
        <div>
            @if ($likedPosts->count())
                <div class="mx-auto max-w-xl border-x border-zinc-800">
                    @foreach ($likedPosts as $post)
                        @include('posts.post', ['post' => $post, 'likedPostIds' => $likedPostIds ?? [], 'bookmarkedPostIds' => $bookmarkedPostIds ?? []])
                    @endforeach
                </div>

                <div class="mx-auto mt-4 flex max-w-xl justify-center">
                    {{ $likedPosts->links() }}
                </div>
            @else
                <p class="text-zinc-500">Você ainda não curtiu nenhum post.</p>
            @endif
        </div>
    @elseif ($activeTab === 'collections')
        <!-- Own collections, private included -->
        <div>
            @if ($collections->count())
                <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($collections as $collection)
                        <a href="{{ route('collections.show', $collection) }}"
                           class="flex flex-col rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow transition hover:border-indigo-500">
                            @php($cover = $collection->coverComic())
                            @if ($cover && $cover->image_path)
                                <img src="{{ asset('storage/' . $cover->image_path) }}"
                                     alt="{{ $cover->title }} cover"
                                     class="mb-3 aspect-[3/4] w-full rounded-lg object-cover">
                            @endif
                            <h2 class="mb-1 text-lg font-semibold">
                                {{ $collection->name }}
                                @if ($collection->is_favorites)
                                    <span title="Favorites">★</span>
                                @endif
                                @if (! $collection->is_public)
                                    <span class="ml-1 rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-zinc-400">privada</span>
                                @endif
                            </h2>
                            @if ($collection->description)
                                <p class="text-sm text-zinc-500">{{ Str::limit($collection->description, 100) }}</p>
                            @endif
                            <p class="mt-2 text-xs text-zinc-500">{{ $collection->comics_count }} comics</p>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $collections->links() }}
                </div>
            @else
                <p class="text-zinc-500">Você não tem coleções ainda.</p>
            @endif
        </div>
    @elseif ($activeTab === 'followers')
        <section>
            <h3 class="mb-4 text-xl font-semibold">Seguidores</h3>
            @include('profile.user-list', ['users' => $followers, 'emptyMessage' => 'Ninguém segue você ainda.'])
        </section>
    @elseif ($activeTab === 'following')
        <section>
            <h3 class="mb-4 text-xl font-semibold">Seguindo</h3>
            @include('profile.user-list', ['users' => $following, 'emptyMessage' => 'Você ainda não segue ninguém.'])
        </section>
    @else
        <!-- Comics section -->
        <div>
            <h3 class="mb-4 text-xl font-semibold">Seus comics</h3>

            @if($comics->count())
                <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">

                    @foreach($comics as $comic)
                        <div
                            class="flex flex-col rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow hover:border-indigo-500 transition">

                            <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}">
                                <img
                                    src="{{ asset('storage/' . $comic->image_path) }}"
                                    alt="{{ $comic->title }}"
                                    class="mb-3 aspect-[3/4] w-full rounded-lg object-cover"
                                />
                            </a>

                            <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}">
                                <h2 class="mb-1 text-lg font-semibold hover:text-indigo-400">
                                    {{ $comic->title }}
                                </h2>
                            </a>

                            <p class="text-sm text-zinc-400">By {{ $comic->author }}</p>

                            <p class="mt-2 text-sm text-zinc-500">
                                {{ Str::limit($comic->description, 100) }}
                            </p>

                            <!-- Tags -->
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($comic->tags as $tag)
                                    @include('components.tag-pill', [
                                        'tag' => $tag,
                                        'class' => 'rounded-full bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-300',
                                    ])
                                @endforeach
                            </div>

                            <a href="{{ route('comics.edit', ['comic' => $comic->id]) }}"
                               class="mt-4 inline-flex justify-center rounded-lg border border-yellow-600 px-3 py-2 text-sm font-medium text-yellow-400 hover:bg-yellow-600/10">
                                Editar comic
                            </a>
                        </div>
                    @endforeach

                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $comics->links() }}
                </div>
            @else
                <p class="text-zinc-500">Você ainda não publicou nenhum comic.</p>
            @endif
        </div>
    @endif

</div>
@endsection
