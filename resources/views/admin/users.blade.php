@extends('layouts.admin-layout')

@section('title', 'Usuários')

@section('page-title', 'Usuários')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Todos os usuários</span>
        </div>
        <div class="admin-table-wrap">
            <table id="usersTable" class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td class="font-medium">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td class="whitespace-nowrap">{{ $user->created_at }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="admin-btn admin-btn-primary admin-btn-sm">Editar</a>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Excluir este usuário?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Excluir</button>
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
            $('#usersTable').DataTable();
        });
    </script>
@endsection
