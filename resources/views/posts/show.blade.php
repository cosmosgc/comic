@extends('layouts.app')

@section('title', 'Post thread')

@section('content')
<div class="mx-auto max-w-xl border-x border-zinc-800 min-h-screen">

    {{-- Header --}}
    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
        <div class="flex items-center gap-4 px-4 py-3">
            <a href="{{ route('posts.index') }}" class="text-xl text-zinc-400 transition hover:text-zinc-100">←</a>
            <h2 class="text-xl font-bold">Post</h2>
        </div>
    </div>

    {{-- Ancestor chain, oldest first --}}
    @foreach ($ancestors as $ancestor)
        <div class="border-b border-zinc-800 opacity-80">
            @include('posts.post', ['post' => $ancestor, 'likedPostIds' => $likedPostIds ?? []])
        </div>
    @endforeach

    {{-- Main post --}}
    <div class="border-b border-zinc-800">
        @include('posts.post', ['post' => $post, 'likedPostIds' => $likedPostIds ?? []])
    </div>

    {{-- Reply composer (same hidden-field pattern as quotes) --}}
    @auth
        <div class="border-b border-zinc-800 p-4">
            @if ($errors->any())
                <div class="mb-3 rounded-xl border border-red-900/60 bg-red-950/40 px-3 py-2 text-sm text-red-300">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <form method="POST"
                  action="{{ route('posts.store') }}"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="parent_id" id="replyParentId" value="{{ $post->id }}">

                <div class="flex gap-3">
                    <img src="{{ Auth::user()->avatar_image_path ? asset(Auth::user()->avatar_image_path) : asset('default-avatar.png') }}"
                         alt="Your avatar"
                         class="h-10 w-10 shrink-0 rounded-full border border-zinc-800 object-cover">

                    <div class="min-w-0 flex-1">
                        {{-- Reply target chip (shown when answering a nested reply) --}}
                        <div id="replyTargetChip" class="mb-1 hidden items-center gap-2 text-sm">
                            <span class="text-zinc-400">
                                Replying to <span id="replyTargetHandle" class="font-semibold text-zinc-200"></span>
                            </span>
                            <button type="button" onclick="clearReplyTarget()"
                                    class="rounded-full px-2 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
                        </div>

                        <textarea
                            name="text"
                            id="replyText"
                            rows="2"
                            maxlength="280"
                            placeholder="Post your reply"
                            class="w-full resize-none overflow-hidden bg-transparent pt-2 text-xl text-zinc-100
                                   placeholder-zinc-500 focus:outline-none"></textarea>

                        <div class="mt-2 flex items-center justify-between border-t border-zinc-800 pt-3">
                            <label for="replyMediaInput"
                                   title="Add images (max 4MB each)"
                                   class="cursor-pointer rounded-full p-2 text-indigo-500 transition hover:bg-indigo-500/10">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </label>
                            <input type="file" name="media[]" multiple id="replyMediaInput"
                                   accept="image/*" class="hidden">

                            <button type="submit"
                                    class="rounded-full bg-indigo-600 px-5 py-1.5 text-sm font-bold text-white
                                           transition hover:bg-indigo-500 disabled:opacity-50">
                                Reply
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endauth

    {{-- Replies --}}
    <div class="border-b border-zinc-800 px-4 py-3">
        <h3 class="font-bold">
            Replies
            @if (($post->replies_count ?? 0) > 0)
                <span class="text-zinc-500">({{ $post->replies_count }})</span>
            @endif
        </h3>
    </div>
    <div>
        @forelse ($replies as $reply)
            @include('posts.thread-node', ['node' => $reply, 'depth' => 1, 'likedPostIds' => $likedPostIds ?? []])
        @empty
            <div class="p-8 text-center text-zinc-500">
                No replies yet. Be the first!
            </div>
        @endforelse
    </div>

    @if ($replies->hasPages())
        <div class="flex justify-center border-t border-zinc-800 p-4">
            {{ $replies->links() }}
        </div>
    @endif

</div>

<script>
(function () {
    // Thread-local fallbacks for the shared action bar. The feed defines
    // richer versions alongside its composer; here they target this page.
    const replyBox = document.getElementById('replyText');
    const replyParentInput = document.getElementById('replyParentId');
    const replyTargetChip = document.getElementById('replyTargetChip');
    const replyTargetHandle = document.getElementById('replyTargetHandle');
    const rootPostId = replyParentInput ? replyParentInput.value : null;

    function scrollToReplyBox() {
        if (!replyBox) return;
        replyBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        replyBox.focus();
    }

    // Point the composer at a specific nested reply (Reddit-style).
    window.replyToNode = function (id, handle) {
        if (replyParentInput) replyParentInput.value = id;
        if (replyTargetHandle) replyTargetHandle.textContent = handle;
        if (replyTargetChip) {
            replyTargetChip.classList.remove('hidden');
            replyTargetChip.classList.add('flex');
        }
        if (replyBox && handle && !replyBox.value.includes(handle)) {
            replyBox.value = (handle + ' ' + replyBox.value).trimEnd() + ' ';
        }
        scrollToReplyBox();
    };

    window.clearReplyTarget = function () {
        if (replyParentInput && rootPostId) replyParentInput.value = rootPostId;
        if (replyTargetChip) {
            replyTargetChip.classList.add('hidden');
            replyTargetChip.classList.remove('flex');
        }
    };

    window.replyToPost = window.replyToPost || function (handle, id) {
        // The shared action bar passes the post id; answer that node.
        if (id) {
            window.replyToNode(id, handle);
            return;
        }
        if (!replyBox || replyBox.value.includes(handle)) {
            scrollToReplyBox();
            return;
        }
        replyBox.value = (handle + ' ' + replyBox.value).trimEnd() + ' ';
        scrollToReplyBox();
    };

    window.quotePost = window.quotePost || function (id, handle, excerpt) {
        if (!replyBox) return;
        const quote = `"${excerpt}" — ${handle} `;
        if (!replyBox.value.includes(quote)) {
            replyBox.value = quote + replyBox.value;
        }
        scrollToReplyBox();
    };

    window.sharePost = window.sharePost || function (btn, text) {
        navigator.clipboard.writeText(text).then(() => {
            const label = btn.querySelector('.share-label');
            if (label) {
                label.classList.remove('hidden');
                setTimeout(() => label.classList.add('hidden'), 1500);
            }
        });
    };
})();
</script>
@endsection
