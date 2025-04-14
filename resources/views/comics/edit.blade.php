@extends('layouts.app')

@section('title', 'Edit Comic')

@section('content')
<div class="container">
    <h1>Edit Comic: {{ $comic->title }}</h1>

    <form action="{{ route('comics.update', $comic->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Comic Details -->
        <div class="mb-4">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" name="title" value="{{ $comic->title }}">
        </div>
        <div class="mb-4">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" name="description">{{ $comic->description }}</textarea>
        </div>

        <!-- Reorderable Pages -->
        <div class="mb-4">
            
            <h3>Reorder Pages</h3>
            <ul id="sortable" class="list-group">
                @foreach($comic->pages as $page)
                    <li class="list-group-item" data-id="{{ $page->id }}">
                        <img src="{{ asset('storage/' . $page->image_path) }}" alt="Page {{ $page->page_number }}" style="width: 100px;">
                        <span>Page {{ $page->page_number }}</span>
                        <button type="button" class="btn btn-danger btn-sm float-right delete-page" data-id="{{ $page->id }}">Delete</button>
                    </li>
                @endforeach
            </ul>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
    <!-- New page upload form -->
    <h3>Add New Page</h3>
    <form action="{{ route('pages.addPage', $comic->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="image">Upload Page Image</label>
            <input type="file" class="form-control" id="image" name="image" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-success">Upload Page</button>
        </div>
    </form>
</div>
@endsection

@section('styles')

<style>
    .list-group{
        display: -ms-flexbox;
        display: flex;
        -ms-flex-direction: column;
        padding-left: 0;
        margin-bottom: 0;
        border-radius: .25rem;
        flex-wrap: wrap;
        flex-direction: row;
        justify-content: space-between;
        gap: 5px;
    }
    .list-group-item {
        position: relative;
        display: block;
        padding: .75rem 1.25rem;
        background-color: #536271;
        border: 1px solid rgb(0 0 0);
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

</style>

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
@endsection
