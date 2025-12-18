<div class="rounded-2xl border border-zinc-800 bg-zinc-900 p-4 shadow">

    <h5 class="mb-3 text-lg font-semibold">
        Criar Post
    </h5>

    <form method="POST"
          action="{{ route('posts.store') }}"
          enctype="multipart/form-data"
          class="space-y-3">
        @csrf

        <!-- Text -->
        <textarea
            name="text"
            rows="3"
            placeholder="O que está acontecendo?"
            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-200
                   placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"></textarea>

        <!-- Media input -->
        <div>
            <input
                type="file"
                name="media[]"
                multiple
                id="mediaInput"
                accept="image/*"
                class="block w-full cursor-pointer rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                       file:mr-4 file:rounded-md file:border-0
                       file:bg-indigo-600 file:px-4 file:py-2
                       file:text-sm file:font-medium file:text-white
                       hover:file:bg-indigo-500"
            >
            <p class="mt-1 text-xs text-zinc-500">
                Tamanho máximo por arquivo: 4MB
            </p>
        </div>

        <!-- Preview -->
        <div id="previewContainer"
             class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3"></div>

        <!-- Submit -->
        <button type="submit"
                class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
            Postar
        </button>
    </form>
</div>
<script>
document.getElementById('mediaInput').addEventListener('change', function (event) {
    const previewContainer = document.getElementById('previewContainer');
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
            img.className =
                'h-32 w-full rounded-lg border border-zinc-800 object-cover';
            previewContainer.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
});
</script>
