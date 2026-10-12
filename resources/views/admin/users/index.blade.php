@extends('layouts.admin-layout')

@section('title', 'Gerenciar usuários')

@section('page-title', 'Usuários')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Gerenciar usuários</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="font-medium">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="admin-btn admin-btn-primary admin-btn-sm">Editar</a>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Tem certeza?')">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
