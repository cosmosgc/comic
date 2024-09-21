<form action="{{ route('comics.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <label for="title">Title:</label>
    <input type="text" name="title" id="title">

    <label for="description">Description:</label>
    <textarea name="description" id="description"></textarea>

    <label for="author">Author:</label>
    <input type="text" name="author" id="author">

    <label for="tags">Tags</label>
    <input type="text" name="tags" placeholder="Tags, use virgula para separar">


    <label for="image">Comic Image:</label>
    <input type="file" name="image" id="image">

    <button type="submit">Submit</button>
</form>
