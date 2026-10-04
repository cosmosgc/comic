<article class="border-b border-zinc-800 px-4 py-3 transition hover:bg-zinc-900/40 hover:cursor-pointer"
         data-thread-url="{{ route('posts.show', $post) }}">
    @include('posts.content', ['post' => $post, 'likedPostIds' => $likedPostIds ?? []])
</article>
