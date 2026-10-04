{{-- Reddit-style tree node: full card, then nested children up to depth 3.
     Deeper chains collapse into a "continue thread" link to the child's page.
     Expects $node (Post), $depth (1 = direct reply), $likedPostIds. --}}
<div class="{{ $depth > 1 ? 'ml-3 border-l-2 border-zinc-800 pl-2 sm:ml-6 sm:pl-3' : '' }}">
    @include('posts.post', ['post' => $node, 'likedPostIds' => $likedPostIds ?? []])

    @if ($depth < 3)
        @foreach ($node->replies as $child)
            @include('posts.thread-node', ['node' => $child, 'depth' => $depth + 1, 'likedPostIds' => $likedPostIds ?? []])
        @endforeach
    @elseif ($node->replies->isNotEmpty())
        <div class="px-4 py-2">
            <a href="{{ route('posts.show', $node->replies->first()) }}"
               class="text-sm font-semibold text-sky-500 transition hover:text-sky-400">
                Continue this thread →
            </a>
        </div>
    @endif
</div>
