<aside class="hidden md:block md:w-64 shrink-0 border-l border-zinc-800 bg-zinc-900">

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets ?? collect(), 'position' => 2])
    </div>

    <!-- Latest Uploads -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Latest Uploads
        </h5>

        @if (($latestComics ?? collect())->isEmpty())
            <ul class="space-y-2 text-sm text-zinc-400">
                <li class="italic text-zinc-500">Nothing uploaded yet.</li>
            </ul>
        @else
            <ul class="space-y-3">
                @foreach ($latestComics as $latest)
                    <li>
                        <a href="{{ route('comics.showBySlug', ['slug' => $latest->slug]) }}"
                           class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-zinc-800">
                            @if ($latest->image_path)
                                <img src="{{ asset('storage/'.$latest->image_path) }}"
                                     alt="{{ $latest->title }}"
                                     class="h-12 w-9 shrink-0 rounded object-cover">
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-200">{{ $latest->title }}</span>
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
            Recommended
        </h5>

        @if (($recommendedComics ?? collect())->isEmpty())
            <ul class="space-y-2 text-sm text-zinc-400">
                <li class="italic text-zinc-500">No recommendations yet.</li>
            </ul>
        @else
            <ul class="space-y-3">
                @foreach ($recommendedComics as $recommended)
                    <li>
                        <a href="{{ route('comics.showBySlug', ['slug' => $recommended->slug]) }}"
                           class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-zinc-800">
                            @if ($recommended->image_path)
                                <img src="{{ asset('storage/'.$recommended->image_path) }}"
                                     alt="{{ $recommended->title }}"
                                     class="h-12 w-9 shrink-0 rounded object-cover">
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-200">{{ $recommended->title }}</span>
                                <span class="block text-xs text-zinc-500">
                                    @if (($recommended->liked_by_users_count ?? 0) > 0)
                                        ♥ {{ $recommended->liked_by_users_count }} likes
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

</aside>
