<aside class="hidden md:block md:w-64 shrink-0 border-l border-zinc-800 bg-zinc-900">

    <!-- Widgets -->
    <div class="p-4">
        @include('components.widget', ['widgets' => $widgets, 'position' => 2])
    </div>

    <!-- Latest Uploads -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Latest Uploads
        </h5>

        <ul class="space-y-2 text-sm text-zinc-400">
            <!-- Latest uploads content here -->
            <li class="italic text-zinc-500">Coming soon…</li>
        </ul>
    </div>

    <!-- Recommended -->
    <div class="px-4 pb-4">
        <h5 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            Recommended
        </h5>

        <ul class="space-y-2 text-sm text-zinc-400">
            <!-- Recommended content here -->
            <li class="italic text-zinc-500">Coming soon…</li>
        </ul>
    </div>

</aside>
