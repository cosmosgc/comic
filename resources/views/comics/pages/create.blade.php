<form action="{{ route('pages.store', $comic->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <label for="page_number">Page Number:</label>
    <input type="number" name="page_number" id="page_number" required>

    <label for="image">Page Image:</label>
    <input type="file" name="image" id="image" required>

    <button type="submit">Upload Page</button>
</form>
