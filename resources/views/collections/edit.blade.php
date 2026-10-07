@extends('layouts.app')

@section('title', 'Edit Collection')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">

    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('collections.show', $collection) }}"
           class="text-xl text-zinc-400 transition hover:text-zinc-100">←</a>
        <h1 class="truncate text-2xl font-bold">Edit Collection: {{ $collection->name }}</h1>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-700 bg-green-900/40 px-4 py-3 text-green-200">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('collections.update', $collection) }}"
          method="POST"
          class="space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        @csrf
        @method('PUT')

        <!-- Name -->
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-zinc-300">
                Collection Name
            </label>
            <input type="text"
                   id="name"
                   name="name"
                   value="{{ old('name', $collection->name) }}"
                   required
                   maxlength="100"
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('name') border-red-500 @else border-zinc-700 @enderror
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="mb-1 block text-sm font-medium text-zinc-300">
                Description
            </label>
            <textarea id="description"
                      name="description"
                      rows="3"
                      maxlength="1000"
                      class="w-full rounded-lg border px-3 py-2 text-sm
                             bg-zinc-950
                             @error('description') border-red-500 @else border-zinc-700 @enderror
                             focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('description', $collection->description) }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if ($supportsOwnership)
            <!-- Visibility -->
            <div class="flex items-start gap-3">
                <input type="checkbox"
                       id="is_public"
                       name="is_public"
                       value="1"
                       @checked(old('is_public', $collection->is_public))
                       class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
                <div>
                    <label for="is_public" class="block text-sm font-medium text-zinc-300">
                        Public collection
                    </label>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        Unchecked = only you can see it.
                    </p>
                </div>
            </div>
        @endif

        @if ($supportsOwnership)
            <!-- Visibility -->
            <div class="flex items-start gap-3">
                <input type="checkbox"
                       id="is_public"
                       name="is_public"
                       value="1"
                       @checked(old('is_public', $collection->is_public))
                       class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
                <div>
                    <label for="is_public" class="block text-sm font-medium text-zinc-300">
                        Public collection
                    </label>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        Unchecked = only you can see it.
                    </p>
                </div>
            </div>
        @endif

        <!-- Current order (drag to reorder) -->
        <div>
            <h2 class="mb-1 text-sm font-medium text-zinc-300">Manage Comic Order</h2>
            <p class="mb-3 text-xs text-zinc-500">Drag covers to reorder — saved automatically.</p>
            <div id="sortable" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @forelse ($selectedComics as $comic)
                    <div class="cursor-move overflow-hidden rounded-xl border border-zinc-700 bg-zinc-950"
                         data-id="{{ $comic->id }}" title="Drag to reorder">
                        <img src="{{ asset('storage/' . $comic->image_path) }}"
                             alt="{{ $comic->title }}"
                             class="aspect-[3/4] w-full object-cover">
                        <p class="truncate px-2 py-1.5 text-xs text-zinc-300">{{ $comic->title }}</p>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">No comics yet — add some below.</p>
                @endforelse
            </div>
        </div>

        <!-- Add / remove comics -->
        <div>
            <h2 class="mb-1 text-sm font-medium text-zinc-300">Comics in this collection</h2>
            <p class="mb-3 text-xs text-zinc-500">Uncheck to remove. Saving keeps everything else untouched.</p>
            @php($selectedIds = $selectedComics->pluck('id')->all())
            <div class="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto rounded-xl border border-zinc-800 bg-zinc-950 p-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($comics as $comic)
                    <label class="flex cursor-pointer flex-col overflow-hidden rounded-xl border transition
                                  {{ in_array($comic->id, $selectedIds) ? 'border-indigo-500' : 'border-zinc-800 hover:border-zinc-600' }}">
                        <input type="checkbox"
                               name="comics[]"
                               value="{{ $comic->id }}"
                               @checked(in_array($comic->id, $selectedIds))
                               class="peer sr-only">
                        <img src="{{ asset('storage/' . $comic->image_path) }}"
                             alt="{{ $comic->title }}"
                             class="aspect-[3/4] w-full object-cover opacity-90 peer-checked:opacity-100">
                        <span class="truncate px-2 py-1.5 text-xs text-zinc-400 peer-checked:text-zinc-100">{{ $comic->title }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('collections.show', $collection) }}"
               class="inline-flex justify-center rounded-lg border border-zinc-700 px-4 py-2 text-sm hover:bg-zinc-800">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Update Collection
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('collections.destroy', $collection) }}"
          onsubmit="return confirm('Delete this collection? Its comics stay untouched.')"
          class="mt-4 text-right">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-400 hover:text-red-300 hover:underline">
            Delete this collection
        </button>
    </form>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
(function () {
    const el = document.getElementById('sortable');
    if (!el || typeof Sortable === 'undefined') return;
    Sortable.create(el, {
        animation: 150,
        onEnd: function () {
            const order = Array.from(el.children).map((child) => child.getAttribute('data-id'));
            fetch('{{ route('collections.sort.update', $collection->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ order: order })
            })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) alert('Error saving order!');
            })
            .catch(() => alert('Error saving order!'));
        }
    });
})();
</script>
@endsection
