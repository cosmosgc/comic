@php
    $row = array_merge([
        'id' => null,
        'type' => 'whatsapp',
        'value' => '',
        'label' => '',
        'is_public' => true,
    ], is_array($row ?? null) ? $row : []);
    $selectedType = (string) ($row['type'] ?? 'whatsapp');
    $placeholder = $types[$selectedType]['placeholder'] ?? '';
@endphp

<div class="contact-row rounded-xl border border-zinc-800 bg-zinc-900/60 p-3" data-contact-row>
    @if (filled($row['id'] ?? null))
        <input type="hidden" name="contact_methods[{{ $index }}][id]" value="{{ $row['id'] }}">
    @endif

    <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)_auto]">
        <div>
            <label class="sr-only" for="contact-type-{{ $index }}">Tipo de contato</label>
            <select id="contact-type-{{ $index }}"
                    name="contact_methods[{{ $index }}][type]"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
                @foreach ($types as $typeKey => $definition)
                    <option value="{{ $typeKey }}" @selected($selectedType === $typeKey)>
                        {{ $definition['icon'] }} {{ $definition['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only" for="contact-value-{{ $index }}">Dado de contato</label>
            <input id="contact-value-{{ $index }}"
                   type="text"
                   name="contact_methods[{{ $index }}][value]"
                   value="{{ $row['value'] ?? '' }}"
                   placeholder="{{ $placeholder }}"
                   maxlength="190"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        </div>

        <button type="button"
                class="remove-contact rounded-lg border border-rose-800/60 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-950/40">
            Remover
        </button>
    </div>

    <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
        <input type="text"
               name="contact_methods[{{ $index }}][label]"
               value="{{ $row['label'] ?? '' }}"
               placeholder="Apelido (opcional, ex.: WhatsApp pessoal)"
               maxlength="60"
               class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-xs">

        <label class="inline-flex items-center gap-2 text-xs text-zinc-300">
            <input type="checkbox"
                   name="contact_methods[{{ $index }}][is_public]"
                   value="1"
                   @checked($row['is_public'] ?? true)
                   class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
            Mostrar no perfil
        </label>
    </div>

    @error("contact_methods.$index.value")
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
    @error("contact_methods.$index.type")
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
    @error("contact_methods.$index.id")
        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
    @enderror
</div>
