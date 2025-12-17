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

        <!-- Progress bar -->
        <div class="hidden h-6 overflow-hidden rounded-lg bg-zinc-800">
            <div id="upload-progress"
                 class="h-full w-0 bg-indigo-600 text-center text-sm font-medium text-white transition-all">
                0%
            </div>
        </div>

        <!-- Submit -->
        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
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
    document.getElementById('comic-upload-form').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default form submission

        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        // const progressBar = document.querySelector('.progress');
        const progress = document.getElementById('upload-progress');

        // progressBar.style.display = 'flex'; // Show progress bar
        progress.parentElement.style.display = 'block'; // Show progress bar container
        xhr.upload.addEventListener('progress', function(event) {
            if (event.lengthComputable) {
                let percent = Math.round((event.loaded / event.total) * 100);
                progress.style.width = percent + '%';
                progress.innerText = percent + '%';
                console.log(percent);
            }
        });

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    alert(response.message);
                    // Depois redireciona pra página do quadrinho
                    window.location.href = response.redirect;
                } else {
                    alert('Erro no upload: ' + xhr.status);
                }
            }
        };


        xhr.open('POST', "{{ route('comics.store') }}", true);
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.send(formData);
    });
</script>
@endsection
