@extends('layouts.app')

@section('title', 'Your Profile')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    <!-- Page title -->
    <h1 class="mb-6 text-2xl font-bold">Seu perfil</h1>

    <!-- Success message -->
    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-700 bg-green-900/40 px-4 py-3 text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Profile card -->
    <div class="mb-8 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        <div class="flex flex-col items-center text-center">

            <img
                src="{{ $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png') }}"
                alt="Avatar"
                class="mb-4 h-24 w-24 rounded-full border-2 border-zinc-700 object-cover"
            />

            <h2 class="text-xl font-semibold">{{ $user->name }}</h2>

            <p class="mt-2 text-sm text-zinc-400">
                <span class="font-medium text-zinc-300">Bio:</span>
                {{ $user->bio ?? 'Sem bio disponível' }}
            </p>

            <!-- Links -->
            <div class="mt-4 w-full max-w-md">
                @if(isset($user->links) && count($user->links) > 0)
                    <h3 class="mb-2 text-sm font-semibold text-zinc-300">Links</h3>
                    <ul class="space-y-1">
                        @foreach($user->links as $link)
                            <li>
                                <a href="{{ $link }}"
                                   target="_blank"
                                   class="break-all text-sm text-indigo-400 hover:underline">
                                    {{ $link }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-zinc-500">No links added.</p>
                @endif
            </div>

            <a href="{{ route('profile.edit') }}"
               class="mt-6 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                Editar perfil
            </a>
        </div>
    </div>

    <!-- Comics section -->
    <div>
        <h3 class="mb-4 text-xl font-semibold">Suas Comics</h3>

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
                                <span
                                    class="rounded-full bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-300">
                                    {{ $tag->name }}
                                </span>
                            @endforeach
                        </div>

                        <a href="{{ route('comics.edit', ['comic' => $comic->id]) }}"
                           class="mt-4 inline-flex justify-center rounded-lg border border-yellow-600 px-3 py-2 text-sm font-medium text-yellow-400 hover:bg-yellow-600/10">
                            Edit Comic
                        </a>
                    </div>
                @endforeach

            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $comics->links() }}
            </div>
        @else
            <p class="text-zinc-500">You have not uploaded any comics yet.</p>
        @endif
    </div>

</div>
@endsection
