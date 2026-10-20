@php
    $editing = isset($listing) && $listing !== null;
    $selectedComics = old('comics', $editing ? $listing->comics->pluck('id')->all() : []);
    $tagValue = old('tags', $editing ? $listing->tags->pluck('name')->implode(', ') : '');
    $contactMethods = $contactMethods ?? collect();
    $selectedContactMethods = array_map('intval', (array) old(
        'contact_methods',
        $editing ? $listing->contactMethods->pluck('id')->all() : []
    ));
    $contactMode = old('contact_mode', $editing ? $listing->contact_mode : 'in_app');
@endphp

@if ($errors->any())
    <div class="mb-5 rounded-md border border-rose-900 bg-rose-950 p-4 text-sm text-rose-200">
        <p class="font-semibold">Confira os campos destacados:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST"
      action="{{ $editing ? route('marketplace.update', ['listing' => $listing->slug]) : route('marketplace.store') }}"
      enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">1 · O que você está anunciando?</h2>
        <p class="mt-1 text-sm text-zinc-500">Título, descrição e categoria ajudam as pessoas a encontrar seu anúncio.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="listing-category" class="mk-label">Categoria *</label>
                <select id="listing-category" name="marketplace_category_id" required class="mk-input">
                    <option value="">Escolha…</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('marketplace_category_id', $editing ? $listing->marketplace_category_id : 0) === (int) $category->id)>
                            {{ $category->icon }} {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('marketplace_category_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="listing-kind" class="mk-label">Tipo de item *</label>
                <select id="listing-kind" name="kind" required class="mk-input">
                    @foreach (\App\Models\MarketplaceListing::kindOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind', $editing ? $listing->kind : 'physical') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('kind')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="listing-type" class="mk-label">Oferta *</label>
                <select id="listing-type" name="listing_type" required class="mk-input">
                    @foreach (\App\Models\MarketplaceListing::typeOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(old('listing_type', $editing ? $listing->listing_type : 'sale') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('listing_type')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="listing-title" class="mk-label">Título *</label>
                <input id="listing-title" name="title" type="text" required minlength="5" maxlength="120"
                       value="{{ old('title', $editing ? $listing->title : '') }}" class="mk-input"
                       placeholder="Ex.: Coleção de figures + comic autografado">
                @error('title')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="listing-description" class="mk-label">Descrição *</label>
                <textarea id="listing-description" name="description" rows="6" required minlength="10" maxlength="5000"
                          class="mk-input" placeholder="Conte o estado, o que está incluso, tamanho, detalhes de entrega…">{{ old('description', $editing ? $listing->description : '') }}</textarea>
                <p class="mt-1 text-xs text-zinc-500">Texto simples. Não cole HTML.</p>
                @error('description')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="listing-condition" class="mk-label">Estado *</label>
                <select id="listing-condition" name="condition" required class="mk-input">
                    @foreach (\App\Models\MarketplaceListing::conditionOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $editing ? $listing->condition : 'good') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="listing-quantity" class="mk-label">Quantidade</label>
                <input id="listing-quantity" name="quantity" type="number" min="1" max="999"
                       value="{{ old('quantity', $editing ? $listing->quantity : 1) }}" class="mk-input">
                @error('quantity')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="listing-tags" class="mk-label">Tags</label>
                <input id="listing-tags" name="tags" type="text" maxlength="240" value="{{ $tagValue }}" class="mk-input"
                       placeholder="ex.: figure, pintura, original, tamanho M">
                <p class="mt-1 text-xs text-zinc-500">Separe por vírgulas. Até 8 tags.</p>
                @error('tags')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">2 · Preço de referência</h2>
        <p class="mt-1 text-sm text-zinc-500">O site não processa pagamentos. Este valor é só um combinado inicial entre as pessoas.</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="listing-price" class="mk-label">Preço ({{ \App\Support\Marketplace::currencyLabel(\App\Support\Marketplace::defaultCurrency()) }})</label>
                <input id="listing-price" name="price" type="number" min="0" step="0.01"
                       value="{{ old('price', $editing ? $listing->price : '') }}" class="mk-input" placeholder="Deixe vazio para “a combinar”">
                @error('price')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-end gap-2 pb-2 text-sm text-zinc-300">
                <input type="checkbox" name="price_negotiable" value="1" @checked(old('price_negotiable', $editing ? $listing->price_negotiable : false))
                       class="h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500">
                Preço negociável
            </label>
        </div>
    </section>

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">3 · Fotos</h2>
        <p class="mt-1 text-sm text-zinc-500">A primeira foto é a capa do anúncio. Máximo de {{ $maxImages }} imagens.</p>

        @if ($editing && $listing->images->isNotEmpty())
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($listing->images as $image)
                    <label class="group relative overflow-hidden rounded-md border border-zinc-800">
                        <img src="{{ $image->url() }}" alt="" class="aspect-square w-full object-cover">
                        <span class="absolute inset-x-0 bottom-0 flex items-center gap-2 bg-zinc-950 px-2 py-1 text-xs">
                            <input type="checkbox" name="remove_images[]" value="{{ $image->id }}"
                                   class="h-3.5 w-3.5 rounded border-zinc-600 bg-zinc-900 text-rose-500">
                            Remover
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="mt-4">
            <label for="listing-images" class="mk-label">Adicionar imagens</label>
            <input id="listing-images" name="images[]" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple
                   class="block w-full cursor-pointer rounded-md border border-zinc-800 bg-zinc-950 px-3 py-2 text-sm text-zinc-500 file:mr-3 file:rounded file:border-0 file:bg-amber-400 file:px-3 file:py-1 file:text-xs file:font-bold file:text-zinc-950">
            @error('images')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            @error('images.*')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div id="image-preview" class="mt-3 grid grid-cols-4 gap-2"></div>
    </section>

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">4 · Comics do site (opcional)</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Vincule comics que você publicou aqui — eles aparecem com capa e link no anúncio.
            Para o tipo “Comic do site”, escolha ao menos um.
        </p>

        @if ($comics->isEmpty())
            <p class="mt-3 rounded-md border border-dashed border-zinc-700 p-4 text-sm text-zinc-500">
                Você ainda não publicou comics. <a href="{{ route('comics.create') }}" class="text-amber-300 hover:underline">Publicar um comic</a>
            </p>
        @else
            <div class="mt-3 max-h-60 space-y-1 overflow-y-auto rounded-lg border border-zinc-800 p-2">
                @foreach ($comics as $comic)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-800/60">
                        <input type="checkbox" name="comics[]" value="{{ $comic->id }}" @checked(in_array($comic->id, $selectedComics))
                               class="h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500">
                        <span class="h-9 w-6 shrink-0 overflow-hidden rounded bg-zinc-800">
                            @if ($comic->image_path)
                                <img src="{{ asset('storage/'.$comic->image_path) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ $comic->title }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('comics')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        @error('comics.*')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
    </section>

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">5 · Local e entrega</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="listing-city" class="mk-label">Cidade</label>
                <input id="listing-city" name="location_city" type="text" maxlength="80" value="{{ old('location_city', $editing ? $listing->location_city : '') }}" class="mk-input">
                @error('location_city')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="listing-region" class="mk-label">Estado / região</label>
                <input id="listing-region" name="location_region" type="text" maxlength="80" value="{{ old('location_region', $editing ? $listing->location_region : '') }}" class="mk-input">
                @error('location_region')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="listing-country" class="mk-label">País</label>
                <input id="listing-country" name="location_country" type="text" maxlength="60" value="{{ old('location_country', $editing ? $listing->location_country : 'Brasil') }}" class="mk-input">
                @error('location_country')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label for="listing-shipping" class="mk-label">Como pretende entregar? *</label>
                <select id="listing-shipping" name="shipping" required class="mk-input">
                    @foreach (\App\Models\MarketplaceListing::shippingOptions() as $value => $label)
                        <option value="{{ $value }}" @selected(old('shipping', $editing ? $listing->shipping : 'arrange') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('shipping')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="mt-2 text-xs text-zinc-500">Nunca informe endereço completo. Cidade/região bastam para combinar.</p>
    </section>

    <section class="mk-panel p-5">
        <h2 class="text-base font-semibold tracking-tight">6 · Contato</h2>
        <p class="mt-1 text-sm text-zinc-500">
            Escolha como as pessoas podem falar com você. Você pode mostrar várias formas salvas no perfil.
        </p>

        <div class="mt-4">
            <label for="listing-contact-mode" class="mk-label">Como quer ser contatado? *</label>
            <select id="listing-contact-mode" name="contact_mode" required class="mk-input">
                @foreach (\App\Models\MarketplaceListing::contactOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($contactMode === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('contact_mode')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div id="listing-contact-email-wrap"
             class="{{ in_array($contactMode, ['in_app_and_email', 'email_only'], true) ? '' : 'hidden' }} mt-4">
            <label for="listing-contact-email" class="mk-label">E-mail (modos antigos)</label>
            <input id="listing-contact-email" name="contact_email" type="email" maxlength="190"
                   value="{{ old('contact_email', $editing ? $listing->contact_email : '') }}" class="mk-input"
                   placeholder="voce@exemplo.com">
            @error('contact_email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="mk-label">Formas de contato neste anúncio</span>
                <a href="{{ route('profile.edit').'#contatos' }}" target="_blank" rel="noopener"
                   class="text-xs font-semibold text-amber-300 hover:text-amber-200">
                    Gerenciar meus contatos
                </a>
            </div>

            @if ($contactMethods->isEmpty())
                <p class="mt-2 rounded-md border border-dashed border-zinc-700 p-4 text-sm text-zinc-500">
                    Você ainda não salvou nenhuma forma de contato.
                    <a href="{{ route('profile.edit').'#contatos' }}" class="text-amber-300 hover:underline">Adicionar no perfil</a>
                    para usar WhatsApp, Telegram, Discord e mais.
                </p>
            @else
                <div class="mt-2 space-y-2">
                    @foreach ($contactMethods as $method)
                        @php($methodId = (int) $method->id)
                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-zinc-800 bg-zinc-950 p-3 transition-colors hover:border-zinc-600">
                            <input type="checkbox"
                                   name="contact_methods[]"
                                   value="{{ $methodId }}"
                                   @checked(in_array($methodId, $selectedContactMethods, true))
                                   class="mt-0.5 h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500">
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-zinc-200">
                                    <span aria-hidden="true">{{ \App\Support\ContactMethod::icon($method->type) }}</span>
                                    {{ $method->label ?: $method->typeLabel() }}
                                    @unless ($method->is_public)
                                        <span class="rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-zinc-500">oculto no perfil</span>
                                    @endunless
                                </span>
                                <span class="mt-0.5 block break-all text-xs text-zinc-500">{{ $method->displayValue() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-zinc-500">
                    Marque uma ou mais. Formas ocultas no perfil continuam disponíveis para este anúncio.
                </p>
            @endif

            @error('contact_methods')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            @error('contact_methods.*')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
        </div>

        <p class="mt-3 text-xs text-zinc-500">
            O chat do site aparece nos modos com chat. Os contatos escolhidos viram botões no anúncio.
        </p>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $editing ? route('marketplace.show', ['listing' => $listing->slug]) : route('marketplace.index') }}" class="mk-btn-outline">Cancelar</a>
        <div class="flex flex-wrap gap-2">
            <button type="submit" name="draft" value="1" class="mk-btn-outline">
                {{ $editing && $listing->status === 'draft' ? 'Salvar rascunho' : 'Salvar como rascunho' }}
            </button>
            <button type="submit" class="mk-btn-primary">
                {{ $editing ? 'Salvar alterações' : 'Publicar anúncio' }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('listing-images');
        var preview = document.getElementById('image-preview');
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            preview.innerHTML = '';
            Array.prototype.slice.call(input.files).forEach(function (file) {
                if (!file.type || file.type.indexOf('image/') !== 0) return;
                var url = URL.createObjectURL(file);
                var img = document.createElement('img');
                img.src = url;
                img.alt = '';
                img.className = 'aspect-square w-full rounded-lg border border-zinc-700 object-cover';
                img.onload = function () { URL.revokeObjectURL(url); };
                preview.appendChild(img);
            });
        });
    })();

    (function () {
        var mode = document.getElementById('listing-contact-mode');
        var email = document.getElementById('listing-contact-email-wrap');
        if (!mode || !email) return;

        function syncEmailField() {
            var legacy = ['in_app_and_email', 'email_only'].indexOf(mode.value) !== -1;
            email.classList.toggle('hidden', !legacy);
        }

        mode.addEventListener('change', syncEmailField);
        syncEmailField();
    })();
</script>
@endpush
