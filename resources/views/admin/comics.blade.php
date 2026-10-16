@extends('layouts.admin-layout')

@section('title', 'Comics')

@section('page-title', 'Comics')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Todos os comics</span>
        </div>
        <div class="admin-table-wrap">
            <table id="comicsTable" class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Autor</th>
                        <th>Slug</th>
                        <th>Criado em</th>
                        <th>Visualizações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comics as $comic)
                        <tr>
                            <td>{{ $comic->id }}</td>
                            <td class="font-medium">{{ $comic->title }}</td>
                            <td>{{ $comic->author }}</td>
                            <td><code>{{ $comic->slug }}</code></td>
                            <td class="whitespace-nowrap">{{ $comic->created_at }}</td>
                            <td>{{ $comic->view_count }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('comics.edit', $comic->id) }}" class="admin-btn admin-btn-sm">Editar</a>
                                <form action="{{ route('admin.comics.destroy', $comic) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Excluir &quot;{{ $comic->title }}&quot; com todas as páginas e arquivos? Isso não pode ser desfeito.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#comicsTable').DataTable({
                "order": [[4, "desc"]] // Order by Created At (5th column, index 4) in descending order
            });
        });
    </script>
@endsection
