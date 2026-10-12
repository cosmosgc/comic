<form action="{{ route('comics.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <label for="title">Título:</label>
    <input type="text" name="title" id="title">

    <label for="description">Descrição:</label>
    <textarea name="description" id="description"></textarea>

    <label for="author">Autor:</label>
    <input type="text" name="author" id="author">

    <label for="tags">Tags</label>
    <input type="text" name="tags" placeholder="Tags, use vírgula para separar">


    <label for="image">Imagem do comic:</label>
    <input type="file" name="image" id="image">

    <button type="submit">Enviar</button>
</form>
