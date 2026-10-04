<article class="border-b border-zinc-800 px-4 py-3 transition hover:bg-zinc-900/40">
    @include('posts.content', ['post' => $post, 'likedPostIds' => $likedPostIds ?? []])
</article>
