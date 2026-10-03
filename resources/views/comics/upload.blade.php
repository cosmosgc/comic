@extends('layouts.app')

@section('title', 'Upload Comic')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">

    <h1 class="mb-6 text-2xl font-bold">Upload Comic</h1>

    <form id="comic-upload-form"
          action="{{ route('comics.store') }}"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">

        @csrf

        <!-- Title -->
        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-zinc-300">
                Comic Title
            </label>
            <input type="text"
                   id="title"
                   name="title"
                   required
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                          focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            <p id="duplicate-warning"
               class="mt-1 hidden text-sm text-red-400"></p>
        </div>

        <!-- Author -->
        <div>
            <label for="author" class="mb-1 block text-sm font-medium text-zinc-300">
                Autor
            </label>
            <input type="text"
                   id="author"
                   name="author"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        </div>

        <!-- Description -->
        <div>
            <label for="desc" class="mb-1 block text-sm font-medium text-zinc-300">
                Descrição
            </label>
            <input type="text"
                   id="desc"
                   name="desc"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        </div>

        <!-- Tags -->
        <div>
            <label for="tags" class="mb-1 block text-sm font-medium text-zinc-300">
                Tags
            </label>
            <input type="text"
                   name="tags"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm"
                   placeholder="Insira tags, separado por vírgula">
        </div>

        <!-- Upload mode -->
        <div>
            <p class="mb-2 text-sm font-medium text-zinc-300">
                Selecione o modo de upload
            </p>

            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio"
                           name="upload_mode"
                           id="upload_folder"
                           value="folder"
                           checked
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                    Enviar Pasta
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="radio"
                           name="upload_mode"
                           id="upload_images"
                           value="images"
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500">
                    Escolher Imagens
                </label>
            </div>
        </div>

        <!-- Folder upload -->
        <div id="folder-upload">
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Selecione uma Pasta
            </label>
            <input type="file"
                   id="folder"
                   name="folder[]"
                   webkitdirectory
                   directory
                   multiple
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
        </div>

        <!-- Images upload -->
        <div id="images-upload" class="hidden">
            <label class="mb-1 block text-sm font-medium text-zinc-300">
                Escolha as Imagens
            </label>
            <input type="file"
                   id="images"
                   name="images[]"
                   multiple
                   accept="image/*"
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
        </div>

        <!-- Image preview -->
        <div>
            <div id="image-preview"
                 class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"></div>
        </div>

        <!-- Selection summary: sizes + total -->
        <div id="upload-summary" class="hidden rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p id="summary-line" class="font-medium text-zinc-200"></p>
                <p id="summary-limits" class="text-xs text-zinc-400"></p>
            </div>
            <p id="summary-warning" class="mt-1 hidden text-sm text-red-400"></p>
            <div class="mt-3 max-h-56 overflow-y-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-zinc-400">
                        <tr>
                            <th class="py-1 pr-2">Arquivo</th>
                            <th class="py-1 pr-2">Tamanho</th>
                            <th class="py-1 pr-2">Dimensões</th>
                            <th class="py-1 pr-2">Situação</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody id="file-list"></tbody>
                </table>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="hidden h-6 overflow-hidden rounded-lg bg-zinc-800">
            <div id="upload-progress"
                 class="h-full w-0 bg-indigo-600 text-center text-sm font-medium text-white transition-all">
                0%
            </div>
        </div>

        <!-- Submit -->
        <button type="submit"
                id="upload-submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40
                       disabled:cursor-not-allowed disabled:opacity-50">
            Upload Comic
        </button>
    </form>
</div>
@endsection


@section('styles')
<style>
    #image-preview img {
        max-width: 150px;
        max-height: 150px;
        margin: 10px;
    }
    .progress {
        height: 25px;
    }
</style>
@endsection

@section('scripts')<script>
document.addEventListener('DOMContentLoaded', function () {
    const uploadFolder = document.getElementById('upload_folder');
    const uploadImages = document.getElementById('upload_images');
    const folderUpload = document.getElementById('folder-upload');
    const imagesUpload = document.getElementById('images-upload');
    
    const titleInput = document.getElementById('title');
    const warning = document.getElementById('duplicate-warning');
    const apiUrl = `{{ route('api.comics') }}`;

    let checkTimeout = null;

    titleInput.addEventListener('input', function () {
        clearTimeout(checkTimeout);

        const title = this.value.trim();
        if (title.length === 0) {
            warning.style.display = 'none';
            return;
        }

        // Add a small delay to avoid too many requests
        checkTimeout = setTimeout(() => {
            fetch(`${apiUrl}?search=${encodeURIComponent(title)}`)
                .then(response => response.json())
                .then(data => {
                    // Check if any comic has EXACTLY the same title
                    const duplicate = data.find(c => c.title.toLowerCase() === title.toLowerCase());

                    if (duplicate) {
                        warning.innerText = `⚠️ Já existe um quadrinho com este título: "${duplicate.title}"`;
                        warning.style.display = 'block';
                    } else {
                        warning.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar duplicidade:', error);
                });
        }, 300);
    });

    // Função pra criar cookie
    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days*24*60*60*1000));
        document.cookie = name + "=" + value + ";expires=" + d.toUTCString() + ";path=/";
    }

    // Função pra ler cookie
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
    }

    // Quando mudar para upload de pasta
    uploadFolder.addEventListener('change', function () {
        folderUpload.style.display = 'block';
        imagesUpload.style.display = 'none';
        setCookie('uploadType', 'folder', 7); // salva por 7 dias
    });

    // Quando mudar para upload de imagens
    uploadImages.addEventListener('change', function () {
        imagesUpload.style.display = 'block';
        folderUpload.style.display = 'none';
        setCookie('uploadType', 'images', 7); // salva por 7 dias
    });

    // Quando carregar a página, verifica o cookie
    const savedType = getCookie('uploadType');
    if (savedType === 'folder') {
        uploadFolder.checked = true;
        folderUpload.style.display = 'block';
        imagesUpload.style.display = 'none';
    } else if (savedType === 'images') {
        uploadImages.checked = true;
        imagesUpload.style.display = 'block';
        folderUpload.style.display = 'none';
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const folderInput = document.getElementById('folder');
    const imagesInput = document.getElementById('images');
    const previewContainer = document.getElementById('image-preview');
    const pattern = /^\[(.*?)\]\s*(.*?)\s*(?:\((.*?)\))?$/;

    function handleFiles(files, useFolderName = true) {
        previewContainer.innerHTML = ''; // Clear previous previews

        for (const file of files) {
            let folderName = '';

            // Se for pasta, usa o caminho relativo pra pegar o nome da pasta
            if (useFolderName && file.webkitRelativePath) {
                folderName = file.webkitRelativePath.split('/')[0];
            } else {
                // Se for imagens soltas, tenta pegar o nome do arquivo sem extensão
                folderName = file.name.split('.').slice(0, -1).join('.');
            }

            const match = folderName.match(pattern);

            if (match) {
                document.getElementById("author").value = match[1] ? match[1].trim() : '';
                document.getElementById("title").value = match[2] ? match[2].trim() : '';
                document.getElementById("desc").value = match[3] ? match[3].trim() : '';
                document.getElementById("title").dispatchEvent(new Event('input')); 
            }

            // Preview das imagens
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgElement = document.createElement('img');
                    imgElement.src = e.target.result;
                    imgElement.style.width = '150px';
                    imgElement.style.margin = '5px';
                    previewContainer.appendChild(imgElement);
                };
                reader.readAsDataURL(file);
            }
        }
    }

    // Quando seleciona uma pasta
    folderInput.addEventListener('change', function(event) {
        handleFiles(event.target.files, true);
    });

    // Quando seleciona imagens individuais
    imagesInput.addEventListener('change', function(event) {
        handleFiles(event.target.files, false);
    });

    // Alternar inputs conforme escolha
    document.querySelectorAll('input[name="upload_mode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'folder') {
                document.getElementById('folder-upload').style.display = 'block';
                document.getElementById('images-upload').style.display = 'none';
                folderInput.required = true;
                imagesInput.required = false;
            } else {
                document.getElementById('folder-upload').style.display = 'none';
                document.getElementById('images-upload').style.display = 'block';
                imagesInput.required = true;
                folderInput.required = false;
            }
            previewContainer.innerHTML = ''; // Limpa previews quando troca modo
        });
    });
    
});
</script>

<script>
    // Must stay below nginx client_max_body_size and PHP post_max_size,
    // otherwise the server rejects the request with 413 before Laravel runs.
    const MAX_FILE_BYTES = {{ config('upload.max_file_mb', 10) * 1024 * 1024 }};
    const MAX_POST_BYTES = {{ config('upload.max_post_mb', 100) * 1024 * 1024 }};
    const MAX_FILE_MB = {{ config('upload.max_file_mb', 10) }};
    const MAX_POST_MB = {{ config('upload.max_post_mb', 100) }};
    // Sequential upload tuning: one small request per page, so even a
    // 1 MB nginx cap survives. Bigger images are recompressed in-browser.
    const SAFE_SINGLE_BYTES = {{ config('upload.safe_single_kb', 900) * 1024 }};
    const SAFE_SINGLE_LABEL = '{{ config('upload.safe_single_kb', 900) }} KB';
    const COMPRESS_MAX_DIM = {{ config('upload.compress_max_dim', 2048) }};
    const HUGE_FILE_BYTES = {{ config('upload.huge_file_mb', 50) * 1024 * 1024 }};
    const PAGES_URL_TEMPLATE = "{{ route('pages.store', ['comic' => '__COMIC_ID__']) }}";

    // Indexes (into the active input's FileList) excluded via the ✕ buttons.
    const removedIndexes = new Set();

    function fmtBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }

    function getActiveInput() {
        return document.querySelector('input[name="upload_mode"]:checked').value === 'folder'
            ? document.getElementById('folder')
            : document.getElementById('images');
    }

    // Files the user actually wants to send (minus removed ones).
    function getFilesToSend() {
        const input = getActiveInput();
        const files = [];
        for (let i = 0; i < input.files.length; i++) {
            if (!removedIndexes.has(i)) files.push(input.files[i]);
        }
        return files;
    }

    function refreshFilePanel() {
        const input = getActiveInput();
        const panel = document.getElementById('upload-summary');
        const list = document.getElementById('file-list');
        const line = document.getElementById('summary-line');
        const limits = document.getElementById('summary-limits');
        const warning = document.getElementById('summary-warning');
        const submit = document.getElementById('upload-submit');

        if (input.files.length === 0) {
            panel.classList.add('hidden');
            submit.disabled = false;
            return;
        }
        panel.classList.remove('hidden');
        list.innerHTML = '';

        let totalBytes = 0;
        let included = 0;
        let problems = [];

        for (let i = 0; i < input.files.length; i++) {
            if (removedIndexes.has(i)) continue;
            const file = input.files[i];
            included++;
            totalBytes += file.size;

            const isImage = file.type.startsWith('image/');
            const isHuge = file.size > HUGE_FILE_BYTES;
            const needsOptimize = isImage && !isHuge && file.size > SAFE_SINGLE_BYTES;
            let status;
            if (!isImage) {
                status = '<span class="text-red-400">não é imagem — será rejeitado</span>';
                problems.push(`"${file.name}" não é imagem`);
            } else if (isHuge) {
                status = '<span class="text-red-400">grande demais p/ o navegador</span>';
                problems.push(`"${file.name}" tem ${fmtBytes(file.size)} — reduza antes de enviar`);
            } else if (needsOptimize) {
                status = '<span class="text-yellow-400">será otimizada</span>';
            } else {
                status = '<span class="text-green-400">ok</span>';
            }

            const row = document.createElement('tr');
            row.className = 'border-t border-zinc-800 text-zinc-300';
            row.innerHTML =
                `<td class="py-1 pr-2 break-all">${file.name}</td>` +
                `<td class="py-1 pr-2 whitespace-nowrap">${fmtBytes(file.size)}</td>` +
                `<td class="py-1 pr-2 whitespace-nowrap" id="dims-${i}">…</td>` +
                `<td class="py-1 pr-2">${status}</td>` +
                `<td class="py-1 text-right"><button type="button" data-remove="${i}" class="text-zinc-500 hover:text-red-400" title="Remover">✕</button></td>`;
            list.appendChild(row);

            if (isImage) {
                const cellId = `dims-${i}`;
                if (window.createImageBitmap) {
                    createImageBitmap(file).then(
                        bmp => {
                            const cell = document.getElementById(cellId);
                            if (cell) cell.textContent = `${bmp.width}×${bmp.height}`;
                            bmp.close();
                        },
                        () => {
                            const cell = document.getElementById(cellId);
                            if (cell) cell.textContent = '—';
                        }
                    );
                } else {
                    const url = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = () => {
                        const cell = document.getElementById(cellId);
                        if (cell) cell.textContent = `${img.naturalWidth}×${img.naturalHeight}`;
                        URL.revokeObjectURL(url);
                    };
                    img.onerror = () => {
                        const cell = document.getElementById(cellId);
                        if (cell) cell.textContent = '—';
                        URL.revokeObjectURL(url);
                    };
                    img.src = url;
                }
            } else {
                document.getElementById(`dims-${i}`).textContent = '—';
            }
        }

        list.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => {
                removedIndexes.add(parseInt(btn.getAttribute('data-remove'), 10));
                refreshFilePanel();
            });
        });

        const removed = input.files.length - included;
        line.textContent = `${included} imagem(ns)${removed > 0 ? ` (${removed} removida(s))` : ''} • total ${fmtBytes(totalBytes)}`;
        limits.textContent = `envio sequencial em partes de ~${SAFE_SINGLE_LABEL} • imagens acima disso são otimizadas no navegador`;

        if (problems.length > 0) {
            // Blocking problems: non-images or browser-crushing files.
            warning.textContent = '⚠️ ' + problems.slice(0, 3).join(' • ') + (problems.length > 3 ? ` (+${problems.length - 3} outros)` : '');
            warning.className = 'mt-1 text-sm text-red-400';
            submit.disabled = true;
        } else if (totalBytes > MAX_POST_BYTES) {
            // Not a problem anymore: sequential upload sends one small
            // request per page, so the old single-POST cap can't trigger.
            warning.textContent = `ℹ️ total de ${fmtBytes(totalBytes)} será enviado em ${included} parte(s) de ~${SAFE_SINGLE_LABEL} — sem estourar o limite do servidor.`;
            warning.className = 'mt-1 text-sm text-zinc-400';
            submit.disabled = false;
        } else {
            warning.classList.add('hidden');
            submit.disabled = false;
        }
    }

    // Keep the panel in sync (separate listeners — the preview logic above has its own).
    document.getElementById('folder').addEventListener('change', () => { removedIndexes.clear(); refreshFilePanel(); });
    document.getElementById('images').addEventListener('change', () => { removedIndexes.clear(); refreshFilePanel(); });
    document.querySelectorAll('input[name="upload_mode"]').forEach(radio => {
        radio.addEventListener('change', () => { removedIndexes.clear(); refreshFilePanel(); });
    });

    function fieldValue(name) {
        const byId = document.getElementById(name);
        if (byId) return byId.value;
        const byName = document.querySelector(`#comic-upload-form [name="${name}"]`);
        return byName ? byName.value : '';
    }

    // --- In-browser image optimization -------------------------------------
    function webpSupported() {
        try {
            return document.createElement('canvas').toDataURL('image/webp').startsWith('data:image/webp');
        } catch (e) {
            return false;
        }
    }
    const OUTPUT_MIME = webpSupported() ? 'image/webp' : 'image/jpeg';
    const OUTPUT_EXT = webpSupported() ? 'webp' : 'jpg';

    function loadBitmap(file) {
        if (window.createImageBitmap) return createImageBitmap(file);
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
            img.src = url;
        });
    }

    function canvasToBlob(canvas, mime, quality) {
        return new Promise((resolve, reject) => {
            if (canvas.toBlob) {
                canvas.toBlob(b => (b ? resolve(b) : reject(new Error('encode'))), mime, quality);
            } else {
                reject(new Error('encode'));
            }
        });
    }

    // Shrink `file` until it fits SAFE_SINGLE_BYTES. Returns {blob, name}.
    async function prepareFile(file) {
        const base = file.name.split('.').slice(0, -1).join('.') || file.name;
        if (file.size <= SAFE_SINGLE_BYTES) return { blob: file, name: file.name };
        // Animated GIFs: only touch when over the validation cap, to preserve animation.
        if (file.type === 'image/gif' && file.size <= MAX_FILE_BYTES) return { blob: file, name: file.name };

        const bitmap = await loadBitmap(file);
        const srcW = bitmap.width || bitmap.naturalWidth;
        const srcH = bitmap.height || bitmap.naturalHeight;
        const steps = [
            { dim: COMPRESS_MAX_DIM, q: 0.85 },
            { dim: COMPRESS_MAX_DIM, q: 0.7 },
            { dim: COMPRESS_MAX_DIM, q: 0.55 },
            { dim: 1600, q: 0.7 },
            { dim: 1280, q: 0.65 },
        ];
        for (const step of steps) {
            const scale = Math.min(1, step.dim / Math.max(srcW, srcH));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(srcW * scale));
            canvas.height = Math.max(1, Math.round(srcH * scale));
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            const blob = await canvasToBlob(canvas, OUTPUT_MIME, step.q);
            if (blob.size <= SAFE_SINGLE_BYTES) {
                if (bitmap.close) bitmap.close();
                return { blob, name: `${base}.${OUTPUT_EXT}` };
            }
        }
        if (bitmap.close) bitmap.close();
        throw new Error(`"${file.name}" não coube em ${SAFE_SINGLE_LABEL} mesmo otimizada`);
    }

    // --- Sequential upload: one small request per page ----------------------
    function xhrPost(url, formData, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.setRequestHeader('Accept', 'application/json');
            if (onProgress) {
                xhr.upload.addEventListener('progress', e => {
                    if (e.lengthComputable) onProgress(e.loaded / e.total);
                });
            }
            xhr.onreadystatechange = () => {
                if (xhr.readyState !== 4) return;
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(xhr.responseText);
                } else {
                    reject({ status: xhr.status, text: xhr.responseText });
                }
            };
            xhr.onerror = () => reject({ status: 0, text: '' });
            xhr.send(formData);
        });
    }

    function setProgress(done, total) {
        const progress = document.getElementById('upload-progress');
        progress.parentElement.style.display = 'block';
        const percent = total === 0 ? 0 : Math.round((done / total) * 100);
        progress.style.width = percent + '%';
        progress.innerText = percent + '%';
    }

    function errorDetail(err) {
        if (err.status === 413) {
            return 'servidor recusou (erro 413 — limite desta hospedagem é menor que ' + SAFE_SINGLE_LABEL + ' por requisição; fale com o provedor)';
        }
        if (err.status === 422) {
            try {
                const body = JSON.parse(err.text);
                return 'validação: ' + Object.values(body.errors || {}).flat().join(' ');
            } catch (e) { return 'validação falhou (422)'; }
        }
        return 'erro ' + err.status;
    }

    document.getElementById('comic-upload-form').addEventListener('submit', async function(event) {
        event.preventDefault();

        const files = getFilesToSend();
        if (files.length === 0) {
            alert('Selecione ao menos uma imagem para enviar.');
            return;
        }
        // Backstop for the red rows in the panel (panel already blocks submit).
        for (const file of files) {
            if (!file.type.startsWith('image/')) { alert(`"${file.name}" não é imagem e seria rejeitado.`); return; }
            if (file.size > HUGE_FILE_BYTES) { alert(`"${file.name}" é grande demais para processar no navegador (${fmtBytes(file.size)}).`); return; }
        }

        const submit = document.getElementById('upload-submit');
        submit.disabled = true;
        const mode = document.querySelector('input[name="upload_mode"]:checked').value;
        const token = document.querySelector('#comic-upload-form input[name="_token"]').value;
        const failures = [];
        let comicId = null;
        let redirect = null;

        try {
            // 1) Optimize every file in the browser (keeps each request tiny).
            setProgress(0, files.length);
            const prepared = [];
            for (const file of files) {
                try {
                    prepared.push(await prepareFile(file));
                } catch (e) {
                    failures.push(`${file.name} (${e.message})`);
                    prepared.push(null);
                }
            }

            // 2) First request creates the comic (metadata + first page).
            const firstIdx = prepared.findIndex(p => p !== null);
            if (firstIdx === -1) throw new Error('Nenhuma imagem pôde ser preparada.');
            const createData = new FormData();
            createData.append('_token', token);
            createData.append('title', fieldValue('title'));
            createData.append('author', fieldValue('author'));
            createData.append('desc', fieldValue('desc'));
            createData.append('tags', fieldValue('tags'));
            createData.append('upload_mode', mode);
            createData.append(mode === 'folder' ? 'folder[]' : 'images[]', prepared[firstIdx].blob, prepared[firstIdx].name);

            let created;
            try {
                created = JSON.parse(await xhrPost("{{ route('comics.store') }}", createData, f => setProgress(f * 0.5 / files.length, 1)));
            } catch (err) {
                throw new Error('Falha ao criar o quadrinho: ' + errorDetail(err));
            }
            comicId = created.comic_id;
            redirect = created.redirect;
            if (!comicId) throw new Error('Servidor não retornou o ID do quadrinho.');
            setProgress(1, files.length);

            // 3) Append the remaining pages, one small request each.
            let done = 1;
            for (let i = 0; i < prepared.length; i++) {
                if (i === firstIdx || prepared[i] === null) continue;
                const pageData = new FormData();
                pageData.append('_token', token);
                pageData.append('image', prepared[i].blob, prepared[i].name);
                try {
                    await xhrPost(PAGES_URL_TEMPLATE.replace('__COMIC_ID__', comicId), pageData,
                        f => setProgress(done + f, files.length));
                    done++;
                    setProgress(done, files.length);
                } catch (err) {
                    failures.push(`${files[i].name} (${errorDetail(err)})`);
                    done++;
                    setProgress(done, files.length);
                }
            }

            if (failures.length === 0) {
                alert('Upload concluído: ' + files.length + ' página(s) enviada(s).');
            } else {
                alert(`Enviadas ${files.length - failures.length} de ${files.length} página(s). Falhas:\n- ` + failures.join('\n- ') + '\nComplete as faltantes pela página do quadrinho.');
            }
            window.location.href = redirect;
        } catch (e) {
            alert(e.message || 'Erro no upload.');
            submit.disabled = false;
        }
    });
</script>
@endsection
