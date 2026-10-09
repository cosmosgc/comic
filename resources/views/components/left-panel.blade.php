<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<aside class="hidden md:block md:w-64 shrink-0 border-r border-zinc-800 bg-zinc-900">

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets ?? collect(), 'position' => 1])
    </div>

    <!-- Popular Tags -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Tags Populares
        </h5>

        @if (($tags ?? collect())->isEmpty())
            <p class="text-sm italic text-zinc-500">No tags yet.</p>
        @else
            <ul class="space-y-2">
                @foreach ($tags as $tag)
                    <li>
                        <a href="{{ route('comics.search', ['tag' => $tag->name]) }}"
                           class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-300
                                  transition hover:bg-zinc-800 hover:text-white">

                            <i class="fa-solid fa-tag text-zinc-400"></i>
                            <span class="min-w-0 flex-1 truncate">{{ $tag->name }}</span>

                            @if (isset($tag->likes_sum) && (int) $tag->likes_sum > 0)
                                <span class="shrink-0 rounded-full bg-rose-500/10 px-2 py-0.5 text-xs font-semibold text-rose-300" title="{{ (int) $tag->likes_sum }} likes">
                                    ♥ {{ (int) $tag->likes_sum }}
                                </span>
                            @elseif (isset($tag->comics_count) && (int) $tag->comics_count > 0)
                                <span class="shrink-0 rounded-full bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-400" title="{{ (int) $tag->comics_count }} comics">
                                    {{ (int) $tag->comics_count }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</aside>
