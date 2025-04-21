<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="col-md-2 d-none d-md-block sidebar-bg sidebar">
@include('components.widget', ['widgets' => $widgets, 'position' => 1])

    <h5 class="p-3">Tags Popular</h5>
    <ul class="list-unstyled px-3">
        @foreach ($tags as $tag)
            <li>{{ $tag->name }}</li>
        @endforeach
    </ul>
</div>
