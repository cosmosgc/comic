@extends('layouts.app')

@section('title', 'Edit post')

@section('content')
<div class="mx-auto min-h-screen max-w-xl border-x border-zinc-800">
    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
        <div class="flex items-center gap-4 px-4 py-3">
            <a href="{{ route('posts.show', $post) }}" class="text-xl text-zinc-400 transition hover:text-zinc-100" aria-label="Back to post">←</a>
            <h2 class="text-xl font-bold">Edit post</h2>
        </div>
    </div>

    <div class="p-4">
        @if ($errors->any())
            <div class="mb-3 rounded-xl border border-red-900/60 bg-red-950/40 px-3 py-2 text-sm text-red-300">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('posts.update', $post) }}">
            @csrf
            @method('PUT')

            <label for="editText" class="sr-only">Post text</label>
            <textarea name="text" id="editText" rows="4" maxlength="280"
                      placeholder="What's happening?"
                      class="w-full resize-y rounded-2xl border border-zinc-800 bg-zinc-900 p-3 text-[15px] text-zinc-100 placeholder-zinc-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('text', $post->text) }}</textarea>

            <div class="mt-3 flex items-center justify-between">
                <span id="editCount" class="text-sm text-zinc-500"></span>
                <div class="flex gap-2">
                    <a href="{{ route('posts.show', $post) }}"
                       class="rounded-full border border-zinc-700 px-5 py-1.5 text-sm font-bold text-zinc-200 transition hover:bg-zinc-900">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-full bg-indigo-600 px-5 py-1.5 text-sm font-bold text-white transition hover:bg-indigo-500">
                        Save
                    </button>
                </div>
            </div>
        </form>

        @if (!empty($post->media))
            <p class="mt-4 text-xs text-zinc-500">Attached images can't be changed after posting.</p>
        @endif
    </div>
</div>

<script>
(function () {
    var text = document.getElementById('editText');
    var count = document.getElementById('editCount');
    function update() {
        var remaining = 280 - text.value.length;
        count.textContent = remaining;
        count.classList.toggle('text-red-400', remaining < 0);
        count.classList.toggle('text-zinc-500', remaining >= 0);
    }
    text.addEventListener('input', update);
    update();
})();
</script>
@endsection
