@extends('layouts.app')

@section('title', 'Edit Collection')

@section('content')
<div class="container">
    <h1>Edit Collection: {{ $collection->name }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('collections.update', $collection) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="name">Collection Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $collection->name }}" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control">{{ $collection->description }}</textarea>
        </div>

        <h2>Manage Comic Order</h2>
        <div id="sortable" class="row">
            @foreach ($selectedComics as $comic)
                <div class="col-md-4 mb-3 comic-card" data-id="{{ $comic->id }}">
                    <div class="card">
                        <img src="{{ asset('storage/' . $comic->image_path) }}" class="card-img-top" alt="{{ $comic->title }}">
                        <div class="card-body">
                            <h5 class="card-title">{{ $comic->title }}</h5>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary">Update Collection</button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    var el = document.getElementById('sortable');
    var sortable = Sortable.create(el, {
        animation: 150,
        onEnd: function (evt) {
            // Implement AJAX request here to update the order in the backend
            const order = Array.from(el.children).map(child => child.getAttribute('data-id'));

            // Use the order array to save the new order
            fetch('{{ route('collections.sort.update', $collection->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ order })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    //alert('Order saved successfully!');
                } else {
                    alert('Error saving order!');
                }
            });
        }
    });
</script>
@endsection
