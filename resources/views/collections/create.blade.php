@extends('layouts.app')

@section('title', 'Criar coleção')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold">Criar coleção</h1>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif

    <form action="{{ route('collections.store') }}" method="POST"
          class="space-y-5 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">
        @csrf
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-zinc-300">Nome da coleção</label>
            <input type="text" name="name" id="name" required value="{{ old('name') }}"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('name')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="description" class="mb-1 block text-sm font-medium text-zinc-300">Descrição</label>
            <textarea name="description" id="description" rows="3"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('description') }}</textarea>
        </div>

        <div>
            <label for="comics" class="mb-1 block text-sm font-medium text-zinc-300">Escolher comics</label>
            <select name="comics[]" id="comics" multiple size="8"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @foreach ($comics as $comic)
                    <option value="{{ $comic->id }}">{{ $comic->title }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-zinc-500">Segure Ctrl (ou Cmd) para escolher vários.</p>
        </div>

        <button type="submit" class="btn-primary">Criar coleção</button>
    </form>
</div>
@endsection
