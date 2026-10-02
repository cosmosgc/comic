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
        <input type="hidden" name="referenced_post_id" id="quoteInput" value="">

        <div class="flex gap-3">
            <img src="{{ Auth::user()->avatar_image_path ? asset(Auth::user()->avatar_image_path) : asset('default-avatar.png') }}"
                 alt="Your avatar"
                 class="h-10 w-10 shrink-0 rounded-full border border-zinc-800 object-cover">

            <div class="min-w-0 flex-1">
                <textarea
                    name="text"
                    id="postText"
                    rows="1"
                    maxlength="280"
                    placeholder="What is happening?!"
                    class="w-full resize-none overflow-hidden bg-transparent pt-2 text-xl text-zinc-100
                           placeholder-zinc-500 focus:outline-none"></textarea>

                {{-- Quote chip (shown when reposting) --}}
                <div id="quoteChip" class="mt-2 hidden items-center justify-between gap-2 rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm">
                    <span class="truncate text-zinc-400">
                        Quoting <span id="quoteChipHandle" class="font-semibold text-zinc-200"></span>:
                        <span id="quoteChipText"></span>
                    </span>
                    <button type="button" onclick="clearQuote()"
                            class="shrink-0 rounded-full px-2 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200">✕</button>
                </div>

                {{-- Media preview --}}
                <div id="previewContainer" class="mt-2 grid grid-cols-2 gap-2"></div>

                <div class="mt-2 flex items-center justify-between border-t border-zinc-800 pt-3">
                    {{-- Attach media --}}
                    <label for="mediaInput"
                           title="Add images (max 4MB each)"
                           class="cursor-pointer rounded-full p-2 text-indigo-500 transition hover:bg-indigo-500/10">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </label>
                    <input type="file" name="media[]" multiple id="mediaInput"
                           accept="image/*" class="hidden">

                    <div class="flex items-center gap-3">
                        <span id="charCount" class="text-sm text-zinc-500"></span>
                        <button type="submit"
                                class="rounded-full bg-indigo-600 px-5 py-1.5 text-sm font-bold text-white
                                       transition hover:bg-indigo-500 disabled:opacity-50">
                            Post
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const text = document.getElementById('postText');
    const count = document.getElementById('charCount');
    const mediaInput = document.getElementById('mediaInput');
    const previewContainer = document.getElementById('previewContainer');
    const quoteInput = document.getElementById('quoteInput');
    const quoteChip = document.getElementById('quoteChip');

    function autogrow() {
        text.style.height = 'auto';
        text.style.height = Math.max(text.scrollHeight, 56) + 'px';
        const remaining = 280 - text.value.length;
        count.textContent = text.value.length > 0 ? remaining : '';
        count.classList.toggle('text-red-400', remaining < 0);
        count.classList.toggle('text-zinc-500', remaining >= 0);
    }
    text.addEventListener('input', autogrow);
    autogrow();

    mediaInput.addEventListener('change', function (event) {
        previewContainer.innerHTML = '';
        for (let file of event.target.files) {
            if (file.size > 4 * 1024 * 1024) {
                alert(`O arquivo ${file.name} excede o limite de 4MB.`);
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'h-32 w-full rounded-2xl border border-zinc-800 object-cover';
                previewContainer.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });

    function scrollToComposer() {
        text.scrollIntoView({ behavior: 'smooth', block: 'center' });
        text.focus();
    }

    window.replyToPost = function (handle) {
        clearQuote();
        if (!text.value.includes(handle)) {
            text.value = (handle + ' ' + text.value).trimEnd() + ' ';
        }
        autogrow();
        scrollToComposer();
    };

    window.quotePost = function (id, handle, excerpt) {
        quoteInput.value = id;
        document.getElementById('quoteChipHandle').textContent = handle;
        document.getElementById('quoteChipText').textContent = excerpt;
        quoteChip.classList.remove('hidden');
        quoteChip.classList.add('flex');
        scrollToComposer();
    };

    window.clearQuote = function () {
        quoteInput.value = '';
        quoteChip.classList.add('hidden');
        quoteChip.classList.remove('flex');
    };

    window.sharePost = function (btn, postText) {
        navigator.clipboard.writeText(postText).then(() => {
            const label = btn.querySelector('.share-label');
            if (label) {
                label.classList.remove('hidden');
                setTimeout(() => label.classList.add('hidden'), 1500);
            }
        });
    };
})();
</script>
