<div class="col-md-2 d-none d-md-block sidebar-bg sidebar">
    <h5 class="p-3">ad</h5>
    <ul class="list-unstyled px-3">
    </ul>

    <h5 class="p-3">Tags Popular</h5>
    <ul class="list-unstyled px-3">
        @foreach ($tags as $tag)
            <li>{{ $tag->name }}</li>
        @endforeach
    </ul>
</div>
