@extends('layouts.app')

@section('title', "What's new")

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">

    <h1 class="mb-1 text-2xl font-bold">What's new</h1>
    <p class="mb-6 text-sm text-zinc-400">Website changes, one post per update.</p>

    {{-- Quick search --}}
    <form method="GET" action="{{ route('changelog.index') }}" class="mb-6 flex gap-2">
        <input type="text"
               name="q"
               value="{{ $query }}"
               placeholder="Search updates…"
               autocomplete="off"
               class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm
                      focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
        <button type="submit"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
            Search
        </button>
        @if ($query !== '')
            <a href="{{ route('changelog.index') }}"
               class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300 transition hover:bg-zinc-900">
                Clear
            </a>
        @endif
    </form>

    @php
        $badges = [
            'Added' => 'bg-green-600/20 text-green-400 border-green-600/30',
            'Fixed' => 'bg-red-600/20 text-red-400 border-red-600/30',
            'Changed' => 'bg-blue-600/20 text-blue-400 border-blue-600/30',
            'Removed' => 'bg-zinc-600/20 text-zinc-400 border-zinc-600/30',
        ];
    @endphp

    <div class="space-y-4">
        @forelse ($changelogs as $entry)
            <article class="rounded-2xl border border-zinc-800 bg-zinc-900 p-5 shadow-lg">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full border px-2 py-0.5 font-semibold {{ $badges[$entry['category']] ?? $badges['Removed'] }}">
                        {{ $entry['category'] }}
                    </span>
                    <span class="text-zinc-500">{{ \Carbon\Carbon::parse($entry['date'])->format('M j, Y') }}</span>
                    @if ($entry['pr'])
                        <span class="text-zinc-500">#{{ $entry['pr'] }}</span>
                    @endif
                </div>
                <h2 class="mb-1 text-lg font-bold">
                    <a href="{{ route('changelog.show', $entry['id']) }}" class="transition hover:text-indigo-400">
                        {{ $entry['title'] }}
                    </a>
                </h2>
                @if ($entry['summary'] !== '')
                    <p class="mb-3 text-sm text-zinc-400">{{ $entry['summary'] }}</p>
                @endif
                @if (count($entry['tags']) > 0)
                    <div class="mb-3 flex flex-wrap gap-1">
                        @foreach ($entry['tags'] as $tag)
                            <a href="{{ route('changelog.index', ['q' => $tag]) }}"
                               class="rounded bg-zinc-800 px-2 py-0.5 text-xs text-zinc-400 transition hover:bg-zinc-700 hover:text-zinc-200">
                                #{{ $tag }}
                            </a>
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('changelog.show', $entry['id']) }}"
                   class="text-sm font-semibold text-indigo-400 transition hover:text-indigo-300">
                    Read more →
                </a>
            </article>
        @empty
            <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-8 text-center text-zinc-500">
                @if ($query !== '')
                    No updates match "{{ $query }}".
                @else
                    No updates yet.
                @endif
            </div>
        @endforelse
    </div>

    @if ($changelogs->hasPages())
        <div class="mt-6 flex justify-center">
            {{ $changelogs->links() }}
        </div>
    @endif

</div>
@endsection
