@extends('layouts.marketplace-layout')

@section('title', $listing->title.' — Mercado')

@section('content')
@php
    $gallery = $listing->images->map(fn ($image) => $image->url())->all();
    if ($gallery === [] && $listing->coverUrl()) {
        $gallery = [$listing->coverUrl()];
    }
    $seller = $listing->seller;
    $avatar = $seller?->avatar_image_path
        ? asset($seller->avatar_image_path)
        : Vite::asset('resources/images/default-avatar.svg');
@endphp

<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <nav class="mb-4 text-xs text-zinc-500" aria-label="Trilha de navegação">
        <a href="{{ route('marketplace.index') }}" class="hover:text-zinc-300">Mercado</a>
        <span aria-hidden="true"> / </span>
        <a href="{{ route('marketplace.browse') }}" class="hover:text-zinc-300">Explorar</a>
        <span aria-hidden="true"> / </span>
        <a href="{{ $listing->category->url() }}" class="hover:text-zinc-300">{{ $listing->category->name }}</a>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-300">{{ $listing->title }}</span>
    </nav>

    @unless ($listing->is_approved)
        <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            Este anúncio está <strong>aguardando aprovação</strong>. Só você e a moderação podem vê-lo por enquanto.
        </div>
    @endunless

    @if ($listing->status === 'sold')
        <div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
            Este anúncio foi marcado como <strong>vendido</strong>.
        </div>
    @elseif ($listing->status === 'reserved')
        <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-200">
            Este anúncio está <strong>reservado</strong>.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">

        <div class="space-y-6">
            <section x-data="{ active: {{ Js::from($gallery[0] ?? '') }} }" class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/40">
                <div class="aspect-[4/3] bg-zinc-800">
                    <template x-if="active">
                        <img :src="active" alt="{{ $listing->title }}" class="h-full w-full object-contain">
                    </template>
                    <template x-if="!active">
                        <div class="flex h-full w-full items-center justify-center text-6xl opacity-40" aria-hidden="true">🏷️</div>
                    </template>
                </div>

                @if (count($gallery) > 1)
                    <div class="flex gap-2 overflow-x-auto p-3">
                        @foreach ($gallery as $url)
                            <button type="button" @click="active = {{ Js::from($url) }}"
                                    class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-700 opacity-80 transition hover:opacity-100"
                                    aria-label="Ver foto {{ $loop->iteration }}">
                                <img src="{{ $url }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-5">
                <h2 class="text-lg font-extrabold">Descrição</h2>
                <div class="mt-2 whitespace-pre-line text-sm leading-relaxed text-zinc-300">{{ $listing->description }}</div>

                @if ($listing->tags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($listing->tags as $tag)
                            <span class="mk-chip">#{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($listing->comics->isNotEmpty())
                <section class="rounded-2xl border border-zinc-800 bg-zinc-900/40 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-extrabold">Comics incluídos</h2>
                        <span class="text-xs text-zinc-500">{{ $listing->comics->count() }} no anúncio</span>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($listing->comics as $comic)
                            <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}"
                               class="flex items-center gap-3 rounded-lg border border-zinc-800 bg-zinc-950/40 p-2 transition hover:border-zinc-700">
                                <div class="h-14 w-10 shrink-0 overflow-hidden rounded bg-zinc-800">
                                    @if ($comic->image_path)
                                        <img src="{{ asset('storage/'.$comic->image_path) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $comic->title }}</p>
                                    <p class="text-xs text-zinc-500">{{ $comic->pages_count }} página(s)</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5 text-sm text-zinc-300">
                <h2 class="font-extrabold text-amber-200">Antes de combinar</h2>
                <p class="mt-1">{{ config('marketplace.safety_note') }}</p>
                <p class="mt-2 text-xs text-zinc-400">O Mercado é só um mural de contato: não há pagamento, frete ou garantia intermediados pelo site.</p>
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <section class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-5">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <a href="{{ $listing->category->url() }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">
                        {{ $listing->category->icon }} {{ $listing->category->name }}
                    </a>
                    <span class="mk-chip">{{ $listing->typeLabel() }}</span>
                    <span class="mk-chip">{{ $listing->kindLabel() }}</span>
                    @if ($listing->condition !== 'na')
                        <span class="mk-chip">{{ $listing->conditionLabel() }}</span>
                    @endif
                    @if ($listing->is_featured)
                        <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-zinc-950">Destaque</span>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-extrabold leading-tight">{{ $listing->title }}</h1>

                <p class="mt-3 text-3xl font-extrabold text-amber-300">{{ $listing->priceLabel() }}</p>
                @if ($listing->quantity > 1)
                    <p class="text-xs text-zinc-500">{{ $listing->quantity }} unidades disponíveis</p>
                @endif

                <dl class="mt-4 space-y-2 border-t border-zinc-800 pt-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Entrega</dt>
                        <dd class="text-right text-zinc-200">{{ $listing->shippingLabel() }}</dd>
                    </div>
                    @if ($listing->locationLabel() !== '')
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">Local</dt>
                            <dd class="text-right text-zinc-200">{{ $listing->locationLabel() }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Publicado</dt>
                        <dd class="text-right text-zinc-200">{{ $listing->published_at?->format('d/m/Y') ?? 'Rascunho' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">Visitas</dt>
                        <dd class="text-right text-zinc-200">{{ $listing->view_count }}</dd>
                    </div>
                </dl>

                @if ($isOwner)
                    <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3">
                        <p class="text-sm font-semibold text-amber-200">Este anúncio é seu</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <a href="{{ route('marketplace.edit', ['listing' => $listing->slug]) }}" class="mk-btn-primary">Editar</a>
                            <a href="{{ route('marketplace.mine') }}" class="mk-btn-outline">Meus anúncios</a>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @if (in_array($listing->status, ['draft', 'hidden', 'expired'], true))
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="active">
                                    <button type="submit" class="mk-btn-ghost border-emerald-600/50 text-emerald-300">Publicar</button>
                                </form>
                            @endif
                            @if ($listing->status === 'active')
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="bump">
                                    <button type="submit" class="mk-btn-ghost">Impulsionar</button>
                                </form>
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reserve">
                                    <button type="submit" class="mk-btn-ghost">Reservar</button>
                                </form>
                            @endif
                            @unless ($listing->status === 'sold')
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="sold">
                                    <button type="submit" class="mk-btn-ghost">Vendido</button>
                                </form>
                            @endunless
                            @if ($listing->status !== 'hidden')
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="hidden">
                                    <button type="submit" class="mk-btn-ghost">Ocultar</button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('marketplace.destroy', ['listing' => $listing->slug]) }}"
                              class="mt-3" onsubmit="return confirm('Remover este anúncio? As fotos também serão apagadas.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-rose-400 hover:text-rose-300">Excluir anúncio</button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 space-y-2">
                        @auth
                            <form method="POST" action="{{ route('marketplace.watch', ['listing' => $listing->slug]) }}">
                                @csrf
                                <button type="submit" class="mk-btn-outline w-full {{ $watched ? 'border-amber-500/60 text-amber-300' : '' }}">
                                    {{ $watched ? '♥ Nos favoritos' : '♡ Favoritar' }}
                                </button>
                            </form>
                        @endauth

                        @if (! in_array($listing->contact_mode, ['email_only', 'contacts_only'], true))
                            @auth
                                @if ($canContact)
                                    @if ($conversation)
                                        <a href="{{ route('marketplace.messages.show', ['conversation' => $conversation]) }}" class="mk-btn-primary block w-full text-center">
                                            Continuar conversa
                                        </a>
                                    @else
                                        <details class="rounded-xl border border-zinc-800 bg-zinc-950/40">
                                            <summary class="cursor-pointer list-none px-4 py-2 text-center text-sm font-semibold text-amber-300">
                                                Falar com o vendedor
                                            </summary>
                                            <form method="POST" action="{{ route('marketplace.contact', ['listing' => $listing->slug]) }}" class="space-y-2 border-t border-zinc-800 p-3">
                                                @csrf
                                                <label for="contact-body" class="mk-label">Mensagem</label>
                                                <textarea id="contact-body" name="body" rows="4" required maxlength="4000" class="mk-input" placeholder="Olá! Ainda está disponível?">{{ old('body') }}</textarea>
                                                @error('body')<p class="text-xs text-rose-400">{{ $message }}</p>@enderror
                                                <button type="submit" class="mk-btn-primary w-full">Enviar mensagem</button>
                                            </form>
                                        </details>
                                    @endif
                                @else
                                    <p class="rounded-lg bg-zinc-900 px-3 py-2 text-xs text-zinc-400">Este anúncio não está disponível para contato.</p>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="mk-btn-primary block w-full text-center">Entrar para falar</a>
                            @endauth
                        @endif

                        @if (in_array($listing->contact_mode, ['in_app_and_email', 'email_only'], true) && $listing->contact_email)
                            <a href="mailto:{{ $listing->contact_email }}" class="mk-btn-outline block w-full text-center">Enviar e-mail</a>
                        @endif

                        @if ($canReport)
                            <details class="pt-1">
                                <summary class="cursor-pointer text-xs text-zinc-500 hover:text-zinc-300">Denunciar anúncio</summary>
                                <form method="POST" action="{{ route('marketplace.report', ['listing' => $listing->slug]) }}" class="mt-2 space-y-2 rounded-lg border border-zinc-800 p-3">
                                    @csrf
                                    <select name="reason" class="mk-input" required>
                                        @foreach (\App\Models\MarketplaceReport::reasonOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="details" rows="2" maxlength="1000" class="mk-input" placeholder="Detalhes (opcional)"></textarea>
                                    <button type="submit" class="mk-btn-outline w-full text-rose-300">Enviar denúncia</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @endif
            </section>

            @if ($listing->contactMethods->isNotEmpty())
                <section class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">Formas de contato</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($listing->contactMethods as $method)
                            @php($url = $method->url($listing->title))
                            @php($label = $method->label ?: $method->typeLabel())
                            @if ($url)
                                <a href="{{ $url }}"
                                   @if (\App\Support\ContactMethod::isExternalUrl($url)) target="_blank" rel="noopener" @endif
                                   class="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 px-3 py-2 text-sm transition hover:border-amber-500/60">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span aria-hidden="true">{{ \App\Support\ContactMethod::icon($method->type) }}</span>
                                        <span class="truncate font-semibold">{{ $label }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-amber-300">Abrir →</span>
                                </a>
                            @else
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/60 px-3 py-2 text-sm">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span aria-hidden="true">{{ \App\Support\ContactMethod::icon($method->type) }}</span>
                                        <span class="truncate font-semibold">{{ $label }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-zinc-400">{{ $method->displayValue() }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-2 text-[11px] text-zinc-500">
                        Contato combinado diretamente com o vendedor. O site não intermedeia pagamentos.
                    </p>
                </section>
            @endif

            <section class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Vendedor</p>
                <a href="{{ route('profile.public.show.username', ['username' => $seller->handleName()]) }}" class="mt-2 flex items-center gap-3">
                    <img src="{{ $avatar }}" alt="" class="h-11 w-11 rounded-full object-cover">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $seller->name }}</p>
                        <p class="text-xs text-zinc-500">
                            Nível {{ $seller->level ?? 1 }}
                            @if ($seller->created_at)
                                · desde {{ $seller->created_at->format('m/Y') }}
                            @endif
                        </p>
                    </div>
                </a>
                <p class="mt-3 text-xs text-zinc-500">
                    {{ $seller->activeListingsCount() }} anúncio(s) ativo(s) no mercado.
                </p>
            </section>
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-10">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-extrabold">Na mesma categoria</h2>
                    <p class="text-sm text-zinc-400">Outras opções em {{ $listing->category->name }}.</p>
                </div>
                <a href="{{ $listing->category->url() }}" class="text-sm font-semibold text-amber-300 hover:text-amber-200">Ver categoria →</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($related as $item)
                    <x-marketplace-card :listing="$item" />
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection
