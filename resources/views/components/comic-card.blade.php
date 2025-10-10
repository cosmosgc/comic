<div class="comic-card" data-comic-id="{{ $comic->id }}" data-comic-pagecount="{{ $comic->pageCount() }}" style="background-size: 0% 100%;">
    <div class="progress-background"></div>
    <div class="card-uploader">
        <!-- Link to the user's profile using their username -->
        <a href="{{ route('profile.public.show.username', ['username' => $comic->user->name]) }}">
            @if($comic->user->avatar_image_path || !$minified)
                <img src="{{ $comic->user->avatar_image_path ? asset( $comic->user->avatar_image_path) : asset('default-avatar.png') }}" alt="{{ $comic->user->name }}'s avatar" class="avatar">
            @else
                <!-- Optional: Placeholder image if avatar does not exist -->
                <img src="{{ asset('default-avatar.png') }}" alt="Default avatar" class="avatar">
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
    <!-- Botão de Compartilhar no Telegram -->
    <div class="share-button">
        <button class="telegram-share" onclick="copyToClipboard(this)" data-link="{{ url('https://t.me/iv?url=' . (route('comics.showBySlug', ['slug' => $comic->slug])) . '&rhash=7dbb018f868695') }}">
            📋 Telegram
        </button>
    </div>
</div>
<style>
    .share-button {
        margin-top: 10px;
        text-align: center;
    }

    .telegram-share {
        display: inline-block;
        padding: 8px 12px;
        background-color: #0088cc;
        color: white;
        text-decoration: none;
        border: none;
        cursor: pointer;
        border-radius: 5px;
        font-weight: bold;
    }

    .telegram-share:hover {
        background-color: #0077b5;
    }
</style>

<script>
    function copyToClipboard(button) {
        const link = button.getAttribute('data-link');
        navigator.clipboard.writeText(link).then(() => {
            alert('Link copiado para a área de transferência!');
        }).catch(err => {
            console.error('Erro ao copiar o link: ', err);
        });
    }
</script>