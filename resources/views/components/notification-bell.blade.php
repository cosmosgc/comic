{{-- Navbar notifications bell. Data comes from NotificationComposer. --}}
@auth
    @php($count = $unreadNotificationCount ?? 0)
    @php($items = $recentNotifications ?? collect())
    <div x-data="{ notificationsOpen: false }" class="relative">
        <button @click="notificationsOpen = !notificationsOpen"
                :aria-expanded="notificationsOpen.toString()"
                aria-label="Notificações"
                class="relative rounded-lg border border-zinc-800 bg-zinc-900 p-2 text-zinc-300 transition hover:text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
            </svg>
            @if ($count > 0)
                <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                    {{ $count > 9 ? '9+' : $count }}
                </span>
            @endif
        </button>

        <div x-show="notificationsOpen"
             x-transition
             @click.outside="notificationsOpen = false"
             x-cloak
             class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-2">
                <span class="text-sm font-semibold text-zinc-200">Notificações</span>
                @if ($count > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-xs text-indigo-400 hover:text-indigo-300">Marcar tudo lido</button>
                    </form>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($items as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-zinc-800/60 {{ $notification->read_at ? 'opacity-60' : '' }}">
                            <span class="text-lg">{{ $notification->data['icon'] ?? '🔔' }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-zinc-100">
                                    {{ $notification->data['title'] ?? 'Notificação' }}
                                </span>
                                <span class="mt-0.5 block text-xs text-zinc-400">
                                    {{ $notification->data['message'] ?? '' }}
                                </span>
                                <span class="mt-0.5 block text-[10px] text-zinc-500">
                                    {{ $notification->created_at?->diffForHumans() }}
                                </span>
                            </span>
                        </button>
                    </form>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-zinc-500">Nada por aqui ainda.</p>
                @endforelse
            </div>
        </div>
    </div>
@endauth
