{{-- Content only: the page layout owns this panel's width, visibility and
     scroll behaviour. --}}

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets ?? collect(), 'position' => 2])
    </div>

    <!-- Trending hashtags -->
    @if (($trendingHashtags ?? collect())->isNotEmpty())
        <div class="px-4 pb-4">
            <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
                Em alta
            </h5>
            <ul class="space-y-2">
                @foreach ($trendingHashtags as $trendingTag)
                    <li>
                        <a href="{{ route('hashtags.show', ['tag' => $trendingTag->tag]) }}"
                           class="flex items-center justify-between rounded-lg p-1 text-sm transition hover:bg-zinc-800">
                            <span class="font-medium text-sky-400">#{{ $trendingTag->tag }}</span>
                            <span class="text-xs text-zinc-500">{{ $trendingTag->recent_count ?? $trendingTag->use_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Upcoming releases: far-off comics stay anonymous, matching calendar. -->
    @if (($upcomingComics ?? collect())->isNotEmpty())
        <div class="px-4 pb-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h5 class="text-sm font-semibold uppercase tracking-wide text-zinc-300">
                    Próximos lançamentos
                </h5>
                <a href="{{ route('comics.calendar') }}" class="text-xs font-semibold text-amber-300 transition hover:text-amber-200">
                    Ver todos
                </a>
            </div>
            <ul class="space-y-3">
                @foreach ($upcomingComics as $upcoming)
                    @php
                        $isFarAhead = $upcoming->isFarAhead();
                        $upcomingUrl = $upcoming->isVisibleTo(auth()->user())
                            ? route('comics.showBySlug', $upcoming->slug)
                            : route('comics.calendar');
                    @endphp
                    <li data-upcoming-sidebar="{{ $upcoming->id }}">
                        <a href="{{ $upcomingUrl }}" class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-zinc-800">
                            @if ($upcoming->image_path)
                                <img src="{{ asset('storage/'.$upcoming->image_path) }}"
                                     alt="{{ $isFarAhead ? 'Capa de um comic futuro' : 'Capa de '.$upcoming->title }}"
                                     class="h-12 w-9 shrink-0 rounded object-cover {{ $isFarAhead ? 'blur-sm' : '' }}">
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-200">
                                    {{ $isFarAhead ? 'Comic em breve' : $upcoming->title }}
                                </span>
                                <span class="block text-xs text-amber-300">
                                    {{ $upcoming->published_at?->format('d/m H:i') }}
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Latest Uploads -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Enviados recentes
        </h5>

        @if (($latestComics ?? collect())->isEmpty())
            <ul class="space-y-2 text-sm text-zinc-400">
                <li class="italic text-zinc-500">Nada enviado ainda.</li>
            </ul>
        @else
            <ul class="space-y-3">
                @foreach ($latestComics as $latest)
                    @php($latestLocked = $latest->isLockedFor(auth()->user()))
                    <li>
                        <a href="{{ $latestLocked ? route('login') : route('comics.showBySlug', ['slug' => $latest->slug]) }}"
                           class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-zinc-800">
                            @if ($latest->image_path)
                                <img src="{{ asset('storage/'.$latest->image_path) }}"
                                     alt="{{ $latest->title }}"
                                     class="h-12 w-9 shrink-0 rounded object-cover">
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-200">{{ $latestLocked ? '🔒 ' : '' }}{{ $latest->title }}</span>
                                <span class="block text-xs text-zinc-500">{{ $latest->created_at?->diffForHumans() }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Recommended -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Recomendados
        </h5>

        @if (($recommendedComics ?? collect())->isEmpty())
            <ul class="space-y-2 text-sm text-zinc-400">
                <li class="italic text-zinc-500">Sem recomendações ainda.</li>
            </ul>
        @else
            <ul class="space-y-3">
                @foreach ($recommendedComics as $recommended)
                    @php($recommendedLocked = $recommended->isLockedFor(auth()->user()))
                    <li>
                        <a href="{{ $recommendedLocked ? route('login') : route('comics.showBySlug', ['slug' => $recommended->slug]) }}"
                           class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-zinc-800">
                            @if ($recommended->image_path)
                                <img src="{{ asset('storage/'.$recommended->image_path) }}"
                                     alt="{{ $recommended->title }}"
                                     class="h-12 w-9 shrink-0 rounded object-cover">
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-200">{{ $recommendedLocked ? '🔒 ' : '' }}{{ $recommended->title }}</span>
                                <span class="block text-xs text-zinc-500">
                                    @if (($recommended->liked_by_users_count ?? 0) > 0)
                                        ♥ {{ $recommended->liked_by_users_count }} curtidas
                                    @else
                                        {{ $recommended->created_at?->diffForHumans() }}
                                    @endif
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
