@extends('layouts.admin-layout')

@section('title', 'Configurações do Mercado')
@section('page-title', 'Configurações do Mercado')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="admin-alert admin-alert-danger mb-4">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketplace.settings.update') }}" class="admin-card max-w-3xl">
        @csrf

        <div class="admin-card-body space-y-5">
            @foreach ($definitions as $key => $definition)
                @php($value = old($key, $values[$key] ?? null))

                @if ($definition['type'] === 'bool')
                    <div>
                        <input type="hidden" name="{{ $key }}" value="0">
                        <label class="admin-check">
                            <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) $value)>
                            {{ $definition['label'] }}
                        </label>
                        <p class="mt-1 text-xs opacity-60">{{ $definition['hint'] }}</p>
                    </div>
                @elseif ($definition['type'] === 'json')
                    <div>
                        <label class="admin-label" for="{{ $key }}">{{ $definition['label'] }}</label>
                        <textarea id="{{ $key }}" name="{{ $key }}" rows="3" class="admin-input w-full">{{ is_array($value) ? implode(', ', $value) : $value }}</textarea>
                        <p class="mt-1 text-xs opacity-60">{{ $definition['hint'] }} Separe por vírgula ou linha.</p>
                    </div>
                @elseif ($definition['type'] === 'int')
                    <div>
                        <label class="admin-label" for="{{ $key }}">{{ $definition['label'] }}</label>
                        <input id="{{ $key }}" type="number" name="{{ $key }}" min="1" max="100000" value="{{ $value }}" class="admin-input w-full">
                        <p class="mt-1 text-xs opacity-60">{{ $definition['hint'] }}</p>
                    </div>
                @else
                    <div>
                        <label class="admin-label" for="{{ $key }}">{{ $definition['label'] }}</label>
                        <input id="{{ $key }}" name="{{ $key }}" value="{{ $value }}"
                               class="admin-input w-full" @if ($key === 'default_currency') maxlength="3" style="text-transform:uppercase" @endif>
                        <p class="mt-1 text-xs opacity-60">{{ $definition['hint'] }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="admin-card-body border-t border-zinc-200 dark:border-zinc-800">
            <button type="submit" class="admin-btn admin-btn-primary">Salvar configurações</button>
        </div>
    </form>

    <p class="mt-4 max-w-3xl text-xs opacity-60">
        Estes valores sobrepõem <code>config/marketplace.php</code> sem precisar de deploy.
        O Mercado não processa pagamentos: preços são apenas de referência.
    </p>
@endsection
