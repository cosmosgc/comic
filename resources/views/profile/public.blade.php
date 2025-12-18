@extends('layouts.app')

@section('title', 'Public Profile of ' . $user->name)

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    <!-- Page title -->
    <h1 class="mb-6 text-2xl font-bold">
        Perfil de {{ $user->name }}
    </h1>

    <!-- Success message -->
    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-700 bg-green-900/40 px-4 py-3 text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Profile card -->
    <div class="mb-10 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        <div class="flex flex-col items-center text-center">

            <img
                src="{{ $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png') }}"
                alt="Avatar"
                class="mb-4 h-24 w-24 rounded-full border-2 border-zinc-700 object-cover"
            >

            <h2 class="text-xl font-semibold">
                {{ $user->name }}
            </h2>

            <p class="mt-2 text-sm text-zinc-400">
                <span class="font-medium text-zinc-300">Bio:</span>
                {{ $user->bio ?? 'No bio available' }}
            </p>

            <!-- Links -->
            <div class="mt-4 w-full max-w-md">
                @if(isset($user->links) && count($user->links) > 0)
                    <h3 class="mb-2 text-sm font-semibold text-zinc-300">
                        Links
                    </h3>

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
                    <p class="text-sm text-zinc-500">
                        No links added.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Comics -->
    <section>
        <h3 class="mb-4 text-xl font-semibold">
            Comics by {{ $user->name }}
        </h3>

        @if($comics->count())
            <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                @foreach($comics as $comic)
                    <x-comic-card :comic="$comic" />
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-8 flex justify-center">
                {{ $comics->links() }}
            </div>
        @else
            <p class="text-zinc-500">
                This user has not uploaded any comics yet.
            </p>
        @endif
    </section>

</div>
@endsection
