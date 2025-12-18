<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<aside class="hidden md:block md:w-64 shrink-0 border-r border-zinc-800 bg-zinc-900">

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets, 'position' => 1])
    </div>

    <!-- Popular Tags -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Tags Populares
        </h5>

        <ul class="space-y-2">
            @foreach ($tags as $tag)
                <li>
                    <a href="{{ route('comics.search', ['tag' => $tag->name]) }}"
                       class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-300
                              transition hover:bg-zinc-800 hover:text-white">

                        <i class="fa-solid fa-tag text-zinc-400"></i>
                        <span>{{ $tag->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

</aside>
