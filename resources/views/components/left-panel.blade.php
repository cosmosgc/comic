<aside class="hidden md:block md:w-64 shrink-0 border-r border-zinc-800 bg-zinc-900">

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets ?? collect(), 'position' => 1])
    </div>

    <!-- Popular Tags -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Tags populares
        </h5>

        @if (($tags ?? collect())->isEmpty())
            <p class="text-sm italic text-zinc-500">Sem tags ainda.</p>
        @else
            <ul class="space-y-2">
                @foreach ($tags as $tag)
                    @if ($tag->displayCategory && ! $tag->displayCategory->is_visible)
                        @continue
                    @endif
                    <li>
                        <a href="{{ route('comics.search', ['tag' => $tag->name]) }}"
                           @if ($tag->displayCategory) title="Categoria: {{ $tag->displayCategory->label }}" @endif
                           class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-300
                                  transition hover:bg-zinc-800 hover:text-white">

                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" @if ($tag->displayCategory) style="color: {{ $tag->displayCategory->text_color }}" @else style="color: #71717a;" @endif>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z"/>
                            </svg>
                            <span class="min-w-0 flex-1 truncate" @if ($tag->displayCategory) style="color: {{ $tag->displayCategory->text_color }}" @endif>{{ $tag->name }}</span>

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

    <!-- Top comics (only pages that provide $topComics, e.g. home) -->
    @if (($topComics ?? collect())->isNotEmpty())
        <div class="px-4 pb-4">
            <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
                Top comics da semana
            </h5>

            <ul class="space-y-1">
                @foreach ($topComics as $rank => $comic)
                    <li>
                        <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}"
                           title="{{ $comic->title }}"
                           class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition hover:bg-zinc-800">
                            <span class="w-5 shrink-0 text-center text-sm font-bold text-zinc-500">
                                {{ $rank + 1 }}
                            </span>
                            <img src="{{ asset('storage/' . $comic->image_path) }}"
                                 alt="{{ $comic->title }} cover"
                                 loading="lazy"
                                 class="h-16 w-12 shrink-0 rounded object-cover">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-zinc-200">
                                    {{ $comic->title }}
                                </span>
                                <span class="mt-0.5 block text-xs text-zinc-500">
                                    👁 {{ $comic->view_count }}
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</aside>
