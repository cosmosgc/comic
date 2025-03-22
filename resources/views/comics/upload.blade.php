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
            <label for="folder">Select a Folder</label>
            <input type="file" id="folder" name="folder[]" webkitdirectory directory multiple required>
        </div>

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

@section('scripts')
<script>
    document.getElementById('folder').addEventListener('change', function(event) {
        const files = event.target.files;
        const previewContainer = document.getElementById('image-preview');
        previewContainer.innerHTML = ''; // Clear previous previews

        const pattern = /^\[(.*?)\]\s*(.*?)\s*(?:\((.*?)\))?$/;

        for (const file of files) {
            const folderName = file.webkitRelativePath.split('/')[0]; // Extract the folder name
            const match = folderName.match(pattern);

            if (match) {
                document.getElementById("author").value = match[1] ? match[1].trim() : '';
                document.getElementById("title").value = match[2] ? match[2].trim() : '';
                document.getElementById("desc").value = match[3] ? match[3].trim() : '';
            }

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgElement = document.createElement('img');
                    imgElement.src = e.target.result;
                    previewContainer.appendChild(imgElement);
                };
                reader.readAsDataURL(file);
            }
        }
    });

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
            if (xhr.readyState === 4 && xhr.status === 200) {
                alert('Upload Complete');
                // progressBar.style.display = 'none';
                // window.location.reload();
            }
        };

        xhr.open('POST', "{{ route('comics.store') }}", true);
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.send(formData);
    });
</script>
@endsection
