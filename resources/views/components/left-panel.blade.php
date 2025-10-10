<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="col-md-2 d-none d-md-block sidebar-bg sidebar">
    @include('components.widget', ['widgets' => $widgets, 'position' => 1])

    <h5 class="p-3">Tags Populares</h5>
    <ul class="list-unstyled px-3">
        @foreach ($tags as $tag)
            <li>
                <a href="{{ route('comics.search', ['tag' => $tag->name]) }}" class="text-decoration-none">
                    <i class="fa-solid fa-tag me-2"></i> {{ $tag->name }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
