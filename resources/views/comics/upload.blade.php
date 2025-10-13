@extends('layouts.app')

@section('title', 'Upload Comic')

@section('content')
<div class="container">
    <h1>Upload Comic</h1>

    <!-- Form to upload the comic -->
    <form id="comic-upload-form" action="{{ route('comics.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="title">Comic Title</label>
            <input type="text" class="form-control" id="title" name="title" required>
            <small id="duplicate-warning" class="text-danger" style="display: none;"></small>
        </div>

        <div class="form-group">
            <label for="author">Autor</label>
            <input type="text" class="form-control" id="author" name="author">
        </div>

        <div class="form-group">
            <label for="desc">Descrição</label>
            <input type="text" class="form-control" id="desc" name="desc">
        </div>
        <div class="form-group">
            <label for="tags">Tags</label>
            <input type="text" class="form-control" name="tags" placeholder="Insira tags, separado por virgula">
        </div>

        <div class="form-group">
    <label>Selecione o modo de upload</label><br>
    <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="upload_mode" id="upload_folder" value="folder" checked>
        <label class="form-check-label" for="upload_folder">Enviar Pasta</label>
    </div>
    <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="upload_mode" id="upload_images" value="images">
        <label class="form-check-label" for="upload_images">Escolher Imagens</label>
    </div>
    </div>

    <div class="form-group" id="folder-upload">
        <label for="folder">Selecione uma Pasta</label>
        <input type="file" id="folder" name="folder[]" webkitdirectory directory multiple>
    </div>

    <div class="form-group" id="images-upload" style="display: none;">
        <label for="images">Escolha as Imagens</label>
        <input type="file" id="images" name="images[]" multiple accept="image/*">
    </div>

    <!-- Preview container for images -->
    <div id="image-preview" class="row"></div>


        <!-- Preview container for images -->
        <div id="image-preview" class="row"></div>

        <!-- Progress bar -->
        <div class="progress mt-3" style="display: none;">
            <div id="upload-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Upload Comic</button>
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
        const progressBar = document.querySelector('.progress');
        const progress = document.getElementById('upload-progress');

        progressBar.style.display = 'flex'; // Show progress bar

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
