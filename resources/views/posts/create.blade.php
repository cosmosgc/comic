
<div class="card post-create">
    <div class="card-body">
        <h5 class="card-title">Criar Post</h5>
        <form method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data">
            @csrf
            <textarea name="text" class="form-control mb-2" placeholder="O que está acontecendo?"></textarea>
            <input type="file" name="media[]" multiple class="form-control mb-2" id="mediaInput" accept="image/*">
            <small class="text-muted">Tamanho máximo por arquivo: 4MB</small>
            <div id="previewContainer" class="mt-2"></div>
            <button type="submit" class="btn btn-primary">Postar</button>
        </form>
    </div>
</div>

<script>
    document.getElementById('mediaInput').addEventListener('change', function(event) {
        const previewContainer = document.getElementById('previewContainer');
        previewContainer.innerHTML = '';
        
        for (let file of event.target.files) {
            if (file.size > 4 * 1024 * 1024) {
                alert(`O arquivo ${file.name} excede o limite de 4MB.`);
                event.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.classList.add('img-fluid', 'mt-2');
                img.style.maxWidth = '150px';
                img.style.borderRadius = '8px';
                previewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    });
</script>
