<div class="post-header">
    <a href="{{ route('profile.public.show.username', ['username' => $post->author->name]) }}" class="post-user">
        @if($post->author->avatar_image_path)
            <img src="{{ $post->author->avatar_image_path ? asset( $post->author->avatar_image_path) : asset('default-avatar.png') }}" class="avatar">
        @else
            <img src="{{ asset('default-avatar.png') }}" alt="Default avatar" class="avatar">
        @endif
        <span>{{ $post->author->name }}</span>
    </a>
</div>
<p class="card-text">{{ $post->text }}</p>
@if (!empty($post->media))
    @php
        $mediaFiles = json_decode($post->media, true);
    @endphp
    @if (is_array($mediaFiles))
        <div class="post-media">
            @foreach ($mediaFiles as $media)
                <img src="{{ asset( $media) }}" class="img-fluid" alt="Post Media">
            @endforeach
        </div>
    @endif
@endif
@if ($post->referencedPost)
    <blockquote class="blockquote">
        <p class="mb-0">{{ $post->referencedPost->text }}</p>
        <footer class="blockquote-footer">{{ $post->referencedPost->username }}</footer>
    </blockquote>
@endif