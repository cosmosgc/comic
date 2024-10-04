<div class="comic-card" data-comic-id="{{ $comic->id }}" data-comic-pagecount="{{ $comic->pageCount() }}" style="background-size: 0% 100%;">
    <div class="progress-background"></div>
    <div class="card-uploader">
        <!-- Link to the user's profile using their username -->
        <a href="{{ route('profile.public.show.username', ['username' => $comic->user->name]) }}">
            @if($comic->user->avatar_image_path || !$minified)
                <img src="{{ asset('storage/' . $comic->user->avatar_image_path) }}" alt="{{ $comic->user->name }}'s avatar" class="avatar">
            @else
                <!-- Optional: Placeholder image if avatar does not exist -->
                <img src="{{ asset('path/to/placeholder.png') }}" alt="Default avatar" class="avatar">
            @endif
            <span>{{ $comic->user->name }}</span>
        </a>
    </div>

    <a title="{{ $comic->title }}" href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}">
        <img src="{{ asset('storage/' . $comic->image_path) }}" alt="{{ $comic->title }}">
        <h2>{{ Str::limit($comic->title, 35) }}</h2>
    </a>
    <!-- Only show the author if not minified -->
    @if (!isset($minified) || !$minified)
        <p>Por {{ $comic->author }}</p>
        <time>{{$comic->created_at}}</time>
    @endif

    <!-- Only show the description and tags if not minified -->
    @if (!isset($minified) || !$minified)
        <p>{{ Str::limit($comic->description, 100) }}</p>

        <div class="comic-tags">
            @foreach ($comic->tags as $tag)
                <span class="badge badge-info">{{ $tag->name }}</span>
            @endforeach
        </div>

        <p>{{ $comic->pageCount() }} páginas</p>
    @endif
</div>
