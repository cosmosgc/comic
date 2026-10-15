@extends('layouts.admin-layout')

@section('title', $category ? 'Editar categoria' : 'Nova categoria')
@section('page-title', $category ? 'Editar categoria' : 'Nova categoria')

@section('content')
    <div class="admin-card mb-6 max-w-2xl">
        <div class="admin-card-header">
            <span>{{ $category ? $category->label : 'Nova categoria de tag' }}</span>
        </div>
        <div class="admin-card-body">
            <form method="POST"
                  action="{{ $category ? route('admin.tag-categories.update', $category->id) : route('admin.tag-categories.store') }}"
                  class="space-y-4">
                @csrf
                @if ($category)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="label" class="admin-label">Rótulo (exibido)</label>
                        <input type="text" class="admin-input" name="label" id="label" required maxlength="50"
                               value="{{ old('label', $category?->label) }}">
                        @error('label')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="admin-label">Nome interno (minúsculas, sem espaços)</label>
                        <input type="text" class="admin-input" name="name" id="name" required maxlength="50" pattern="[a-z0-9_-]+"
                               value="{{ old('name', $category?->name) }}" placeholder="ex.: artist">
                        @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="admin-label">Cores</label>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @foreach (['background' => 'Fundo', 'border' => 'Borda', 'text_color' => 'Texto'] as $field => $fieldLabel)
                            <div>
                                <span class="mb-1 block text-xs opacity-70">{{ $fieldLabel }}</span>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="pick_{{ $field }}" value="{{ old($field, $category?->$field ?? '#808080') }}"
                                           class="h-9 w-12 cursor-pointer rounded border border-zinc-300 bg-transparent p-0.5 dark:border-zinc-700">
                                    <input type="text" class="admin-input" name="{{ $field }}" id="hex_{{ $field }}" required maxlength="7"
                                           pattern="#[0-9a-fA-F]{6}" value="{{ old($field, $category?->$field) }}" placeholder="#rrggbb">
                                </div>
                                @error($field)<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3">
                        <span class="mb-1 block text-xs opacity-70">Prévia</span>
                        <span id="colorPreview" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium">exemplo de tag</span>
                    </div>
                </div>

                <div>
                    <label for="namespaces" class="admin-label">Namespaces (separados por vírgula ou linha — ex.: <code>artist, group</code>)</label>
                    <textarea class="admin-input" name="namespaces" id="namespaces" rows="2" maxlength="2000">{{ old('namespaces', $category ? implode(', ', $category->namespaces ?? []) : '') }}</textarea>
                    <p class="mt-1 text-xs opacity-60">Tags como <code>namespace:nome</code> caem nesta categoria. Prioridade máxima.</p>
                    @error('namespaces')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="keywords" class="admin-label">Palavras-chave (separadas por vírgula ou linha)</label>
                    <textarea class="admin-input" name="keywords" id="keywords" rows="3" maxlength="5000">{{ old('keywords', $category ? implode(', ', $category->keywords ?? []) : '') }}</textarea>
                    <p class="mt-1 text-xs opacity-60">Busca insensível a maiúsculas; igualdade exata vence antes de substring (ex.: <code>pokemon_(species)</code> não cai em <code>pokemon</code>).</p>
                    @error('keywords')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sort_order" class="admin-label">Ordem (prioridade na busca por substring)</label>
                    <input type="number" class="admin-input" name="sort_order" id="sort_order" min="0" max="9999"
                           value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                    @error('sort_order')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="admin-btn admin-btn-success">{{ $category ? 'Salvar' : 'Criar categoria' }}</button>
                    <a href="{{ route('admin.tag-categories') }}" class="admin-btn">Voltar</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    var fields = ['background', 'border', 'text_color'];
    function paint() {
        var preview = document.getElementById('colorPreview');
        if (!preview) return;
        var bg = document.getElementById('hex_background').value || '#252525';
        var border = document.getElementById('hex_border').value || '#5a5a5a';
        var text = document.getElementById('hex_text_color').value || '#f0f0f0';
        preview.style.backgroundColor = bg;
        preview.style.border = '1px solid ' + border;
        preview.style.color = text;
    }
    fields.forEach(function (field) {
        var pick = document.getElementById('pick_' + field);
        var hex = document.getElementById('hex_' + field);
        if (!pick || !hex) return;
        pick.addEventListener('input', function () { hex.value = pick.value; paint(); });
        hex.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) pick.value = hex.value;
            paint();
        });
    });
    paint();
})();
</script>
@endsection
