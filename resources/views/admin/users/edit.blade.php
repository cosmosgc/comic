@extends('layouts.admin-layout')

@section('title', 'Editar usuário')

@section('page-title', 'Editar usuário')

@section('content')
    <div class="admin-card max-w-2xl">
        <div class="admin-card-header">
            <span>Editar usuário: {{ $user->name }}</span>
        </div>
        <div class="admin-card-body">
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="admin-label">Nome</label>
                    <input type="text" name="name" id="name" class="admin-input" value="{{ old('name', $user->name) }}" required>
                </div>

                <div>
                    <label for="email" class="admin-label">E-mail</label>
                    <input type="email" name="email" id="email" class="admin-input" value="{{ old('email', $user->email) }}" required>
                </div>

                <div>
                    <label for="password" class="admin-label">Senha <span class="admin-muted">(deixe em branco para manter)</span></label>
                    <input type="password" name="password" id="password" class="admin-input">
                </div>

                <div>
                    <label for="password_confirmation" class="admin-label">Confirmar senha</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="admin-input">
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="admin-btn admin-btn-primary">Atualizar usuário</button>
                    <a href="{{ route('admin.users.index') }}" class="admin-btn admin-btn-secondary">Voltar à lista</a>
                </div>
            </form>
        </div>
    </div>
@endsection
