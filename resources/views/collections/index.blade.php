@extends('layouts.app')

@section('title', 'Collections Index')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Collections</h1>
        @auth
            <a href="{{ route('collections.create') }}" class="btn-primary">New collection</a>
        @endauth
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif

    @if ($collections->count() > 0)
        <ul class="space-y-3">
            @foreach ($collections as $collection)
                <li>
                    <a href="{{ route('collections.show', $collection) }}"
                       class="block rounded-xl border border-zinc-800 bg-zinc-900 p-4 transition hover:-translate-y-0.5 hover:border-indigo-500/60">
                        <span class="font-semibold text-zinc-100">{{ $collection->name }}</span>
                        @if ($collection->description)
                            <span class="mt-1 block truncate text-sm text-zinc-400">{{ $collection->description }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="rounded-xl border border-dashed border-zinc-800 px-4 py-10 text-center text-sm text-zinc-500">No collections yet.</p>
    @endif
</div>
@endsection
