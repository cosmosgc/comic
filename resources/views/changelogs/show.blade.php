@extends('layouts.app')

@section('title', $changelog['title'])

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">

    <a href="{{ route('changelog.index') }}"
       class="mb-4 inline-block text-sm font-semibold text-indigo-400 transition hover:text-indigo-300">
        ← All updates
    </a>

    @php
        $badges = [
            'Added' => 'bg-green-600/20 text-green-400 border-green-600/30',
            'Fixed' => 'bg-red-600/20 text-red-400 border-red-600/30',
            'Changed' => 'bg-blue-600/20 text-blue-400 border-blue-600/30',
            'Removed' => 'bg-zinc-600/20 text-zinc-400 border-zinc-600/30',
        ];
    @endphp

    <article class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full border px-2 py-0.5 font-semibold {{ $badges[$changelog['category']] ?? $badges['Removed'] }}">
                {{ $changelog['category'] }}
            </span>
            <span class="text-zinc-500">{{ \Carbon\Carbon::parse($changelog['date'])->format('M j, Y') }}</span>
            @if ($changelog['pr'])
                <a href="https://github.com/cosmosgc/comic/pull/{{ $changelog['pr'] }}"
                   target="_blank" rel="noopener"
                   class="text-zinc-500 transition hover:text-zinc-300">
                    PR #{{ $changelog['pr'] }} ↗
                </a>
            @endif
        </div>

        <h1 class="mb-4 text-2xl font-bold">{{ $changelog['title'] }}</h1>

        @if ($changelog['summary'] !== '')
            <p class="mb-4 border-l-2 border-indigo-500 pl-3 text-sm italic text-zinc-300">
                {{ $changelog['summary'] }}
            </p>
        @endif

        @if ($changelog['body'] !== '')
            <div class="markdown-body mb-4">
                {!! \App\Support\Markdown::toHtml($changelog['body']) !!}
            </div>
        @endif

        @if (count($changelog['tags']) > 0)
            <div class="flex flex-wrap gap-1">
                @foreach ($changelog['tags'] as $tag)
                    <a href="{{ route('changelog.index', ['q' => $tag]) }}"
                       class="rounded bg-zinc-800 px-2 py-0.5 text-xs text-zinc-400 transition hover:bg-zinc-700 hover:text-zinc-200">
                        #{{ $tag }}
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <h2 class="mb-3 mt-8 text-lg font-bold">Related updates</h2>
        <div class="space-y-3">
            @foreach ($related as $item)
                <a href="{{ route('changelog.show', $item['id']) }}"
                   class="block rounded-xl border border-zinc-800 bg-zinc-900 p-4 transition hover:border-zinc-700">
                    <span class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($item['date'])->format('M j, Y') }} · {{ $item['category'] }}</span>
                    <span class="block font-semibold">{{ $item['title'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

</div>
@endsection
