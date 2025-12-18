@extends('layouts.app')

@section('title', 'Edit Comic')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6">

    <h1 class="mb-6 text-2xl font-bold">
        Edit Comic: {{ $comic->title }}
    </h1>

    <!-- Edit comic form -->
    <form action="{{ route('comics.update', $comic->id) }}"
          method="POST"
          enctype="multipart/form-data"
          class="mb-10 space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        @csrf
        @method('PUT')

        <!-- Title -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Title
            </label>
            <input type="text"
                   name="title"
                   value="{{ $comic->title }}"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
        </div>

        <!-- Description -->
        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Description
            </label>
            <textarea name="description"
                      rows="4"
                      class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                             focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ $comic->description }}</textarea>
        </div>

        <button type="submit"
                class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            Save Changes
        </button>
    </form>

    <!-- Reorder pages -->
    <div class="mb-10">
        <h2 class="mb-4 text-xl font-semibold">
            Reorder Pages
        </h2>

        <ul id="sortable"
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">

            @foreach($comic->pages as $page)
                <li data-id="{{ $page->id }}"
                    class="group relative flex flex-col items-center rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow">

                    <!-- Cover badge -->
                    @if($comic->image_path === $page->image_path)
                        <span class="absolute left-2 top-2 rounded-full bg-emerald-600 px-2 py-1 text-xs font-semibold text-white">
                            Cover
                        </span>
                    @endif

                    <img src="{{ asset('storage/' . $page->image_path) }}"
                        alt="Page {{ $page->page_number }}"
                        class="mb-2 w-full rounded-lg object-cover">

                    <span class="mb-2 text-sm text-zinc-300">
                        Page {{ $page->page_number }}
                    </span>

                    <div class="flex gap-2">
                        <button type="button"
                                data-page-id="{{ $page->id }}"
                                class="set-cover rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white
                                    hover:bg-indigo-500">
                            Set as Cover
                        </button>

                        <button type="button"
                                data-id="{{ $page->id }}"
                                class="delete-page rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white
                                    hover:bg-red-500">
                            Delete
                        </button>
                    </div>
                </li>

            @endforeach

        </ul>
    </div>

    <!-- Add new page -->
    <div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        <h2 class="mb-4 text-xl font-semibold">
            Add New Page
        </h2>

        <form action="{{ route('pages.addPage', $comic->id) }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-300">
                    Upload Page Image
                </label>
                <input type="file"
                       id="image"
                       name="image"
                       required
                       accept="image/*"
                       class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                              file:mr-4 file:rounded-md file:border-0
                              file:bg-indigo-600 file:px-4 file:py-2
                              file:text-sm file:font-medium file:text-white
                              hover:file:bg-indigo-500">
                <p class="mt-1 text-xs text-zinc-500">
                    Accepted formats: JPG, JPEG, PNG. Maximum size: 10MB.
                </p>
            </div>

            <button type="submit"
                    class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white
                           hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                Upload Page
            </button>
        </form>
    </div>

</div>
@endsection


@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    const deletePageBaseUrl = "{{ url('page') }}"; // This will give http://localhost:3000/comic/public/page
</script>

<script>
    // Initialize Sortable.js on the pages list
    var sortable = new Sortable(document.getElementById('sortable'), {
        animation: 150,
        onEnd: function (/**Event*/evt) {
            // Get the reordered page IDs
            let orderedPages = [];
            document.querySelectorAll('#sortable li').forEach(function (li, index) {
                orderedPages.push({
                    id: li.getAttribute('data-id'),
                    page_number: index + 1 // Set new page number
                });
            });

            // Send the reordered data to the server
            fetch('{{ route('comics.reorderPages', $comic->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(orderedPages)
            });
        }
    });

    // Delete page functionality
    document.querySelectorAll('.delete-page').forEach(function (button) {
        button.addEventListener('click', function () {
            let pageId = this.getAttribute('data-id');
            console.log('{{ route('pages.deletePage', $comic->id) }}');
            fetch(`${deletePageBaseUrl}/${pageId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the page from the DOM
                    this.closest('li').remove();
                }
            });
        });
    });
</script>
<script>
document.querySelectorAll('.set-cover').forEach(button => {
    button.addEventListener('click', function () {
        const pageId = this.getAttribute('data-page-id');

        fetch('{{ route('comics.setCover', $comic->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ page_id: pageId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Optional: visual feedback
                document.querySelectorAll('[data-page-id]').forEach(btn => {
                    btn.closest('li').querySelector('.absolute')?.remove();
                });

                const badge = document.createElement('span');
                badge.className = 'absolute left-2 top-2 rounded-full bg-emerald-600 px-2 py-1 text-xs font-semibold text-white';
                badge.innerText = 'Cover';

                this.closest('li').appendChild(badge);
            }
        });
    });
});
</script>

@endsection
