{{-- Shared profile header card with banner background.
     Expects $user. Set $showEdit to show the Edit button (own profile). --}}
@php($bannerUrl = $user->banner_image_path ? asset($user->banner_image_path) : null)
@php($avatarUrl = $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png'))

<div class="mb-8 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-lg">
    {{-- Banner --}}
    <div class="relative h-36 bg-gradient-to-r from-indigo-900 via-zinc-900 to-zinc-900 sm:h-48">
        @if ($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="Banner do perfil de {{ $user->name }}"
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 via-zinc-900/20 to-transparent"></div>
        @endif
    </div>

    <div class="flex flex-col items-center px-6 pb-6 text-center">
        <img src="{{ $avatarUrl }}"
             alt="Avatar de {{ $user->name }}"
             class="relative z-10 -mt-12 h-24 w-24 rounded-full border-4 border-zinc-900 object-cover shadow-lg">

        <h2 class="mt-3 text-xl font-semibold">{{ $user->name }}</h2>

        @php($progress = $user->levelProgress())
        <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
            <span class="rounded-full bg-indigo-600 px-3 py-1 text-xs font-bold text-white">
                Nível {{ $progress['level'] }}
            </span>
            <span class="rounded-full bg-zinc-800 px-3 py-1 text-xs font-semibold text-zinc-300">
                {{ number_format($progress['exp']) }} XP
            </span>
            @if ((int) $user->current_streak > 0)
                <span class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-300"
                      title="Sequência de dias ativos — recorde: {{ $user->longest_streak }} dias">
                    🔥 {{ $user->current_streak }} dia(s)
                </span>
            @endif
        </div>

        <div class="mt-3 w-full max-w-md">
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $progress['percent'] }}%"></div>
            </div>
            <p class="mt-1 text-xs text-zinc-500">
                {{ $progress['into_level'] }}/{{ $progress['needed'] }} XP — faltam {{ number_format($progress['remaining']) }} para o nível {{ $progress['next_level'] }}
            </p>
        </div>

        <p class="mt-2 max-w-xl text-sm text-zinc-400">
            {{ $user->bio ?? 'Sem bio disponível' }}
        </p>

        @php($featured = $user->featuredBadges())
        @if ($featured->isNotEmpty())
            @php($hasShowcase = is_array($user->showcase_badges) && $user->showcase_badges !== [])
            <div class="mt-5 w-full max-w-md">
                <h3 class="mb-2 text-sm font-semibold text-zinc-300">
                    @if ($hasShowcase)
                        Destaques ({{ $featured->count() }})
                    @else
                        Conquistas ({{ $featured->count() }}/{{ count(config('gamification.badges', [])) }})
                    @endif
                </h3>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($featured as $badge)
                        @php($def = $badge->definition())
                        <span title="{{ $def['label'] }} — {{ $def['description'] }}"
                              class="inline-flex items-center gap-1 rounded-full border border-zinc-700 bg-zinc-800/70 px-2.5 py-1 text-xs text-zinc-200">
                            <span>{{ $def['icon'] }}</span> {{ $def['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Links --}}
        <div class="mt-4 w-full max-w-md">
            @if(isset($user->links) && count($user->links) > 0)
                <h3 class="mb-2 text-sm font-semibold text-zinc-300">Links</h3>
                <ul class="space-y-1">
                    @foreach($user->links as $link)
                        <li>
                            <a href="{{ $link }}"
                               target="_blank" rel="noopener"
                               class="break-all text-sm text-indigo-400 hover:underline">
                                {{ $link }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-zinc-500">Nenhum link adicionado.</p>
            @endif
        </div>

        @php($followsTab = $followsTab ?? false)
        @if ($followsTab)
            <div class="mt-4 flex items-center gap-5 text-sm">
                <a href="{{ request()->url() }}?tab=followers" class="transition hover:underline">
                    <span class="font-bold text-zinc-100" data-followers-count>{{ $followersCount ?? 0 }}</span>
                    <span class="text-zinc-500">seguidores</span>
                </a>
                <a href="{{ request()->url() }}?tab=following" class="transition hover:underline">
                    <span class="font-bold text-zinc-100">{{ $followingCount ?? 0 }}</span>
                    <span class="text-zinc-500">seguindo</span>
                </a>
            </div>
        @endif

        @auth
            @if ($canFollow ?? false)
                @php($followingNow = $isFollowing ?? false)
                <button type="button" data-style="chip"
                        onclick="toggleFollow('{{ route('users.follow', $user->getKey()) }}', this)"
                        data-following="{{ $followingNow ? '1' : '0' }}"
                        class="mt-4 inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold transition {{ $followingNow ? 'border border-zinc-700 text-zinc-200 hover:border-rose-700 hover:text-rose-300' : 'bg-indigo-600 text-white hover:bg-indigo-500' }}">
                    {{ $followingNow ? 'Seguindo' : 'Seguir' }}
                </button>
            @endif

            @if ($canMute ?? false)
                <form method="POST" action="{{ route('users.mute', $user->getKey()) }}" class="mt-3">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center rounded-lg border px-4 py-2 text-sm font-semibold transition {{ ($isMuted ?? false)
                                ? 'border-rose-700/60 text-rose-300 hover:border-zinc-700 hover:text-zinc-200'
                                : 'border-zinc-700 text-zinc-300 hover:border-rose-700 hover:text-rose-300' }}">
                        {{ ($isMuted ?? false) ? 'Deixar de silenciar' : 'Silenciar' }}
                    </button>
                </form>
            @endif
        @endauth

        @if (!empty($showEdit))
            <a href="{{ route('profile.edit') }}"
               class="mt-6 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                Editar perfil
            </a>
        @endif
    </div>
</div>
