<form action="{{ route('pages.store', $comic->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <label for="page_number">Número da página:</label>
    <input type="number" name="page_number" id="page_number" required>

    <label for="image">Imagem da página:</label>
    <input type="file" name="image" id="image" required>

    <button type="submit">Enviar página</button>
</form>
