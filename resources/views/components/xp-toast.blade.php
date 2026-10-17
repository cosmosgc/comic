{{-- XP pop: reads the award flashed by App\Services\Gamification and
     animates it in once, then fades out. Included by layouts.app. --}}
@php($toast = session('gamification_toast'))
@if (! empty($toast) && ((int) ($toast['exp'] ?? 0) > 0 || ! empty($toast['leveled_up']) || ! empty($toast['badges'])))
    @once
        <style>
            @keyframes xp-pop {
                0% { transform: scale(0.8); opacity: 0; }
                60% { transform: scale(1.06); opacity: 1; }
                100% { transform: scale(1); }
            }
            .xp-pop { animation: xp-pop 0.35s ease-out; }
        </style>
    @endonce
    <div x-data="{ show: false }"
         x-init="setTimeout(() => show = true, 100); setTimeout(() => show = false, 5600)"
         x-show="show"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="xp-pop pointer-events-none fixed bottom-5 right-5 z-[95] w-72 max-w-[calc(100vw-2rem)]">
        <div class="overflow-hidden rounded-2xl border border-indigo-500/40 bg-zinc-900/95 p-4 shadow-2xl backdrop-blur">
            @if ((int) ($toast['exp'] ?? 0) > 0)
                <div class="flex items-center gap-2">
                    <span class="text-2xl">⚡</span>
                    <div>
                        <p class="text-sm font-bold text-indigo-300">+{{ (int) $toast['exp'] }} XP</p>
                        <p class="text-xs text-zinc-400">Experiência ganha!</p>
                    </div>
                </div>
            @endif

            @if (! empty($toast['leveled_up']))
                <div class="mt-2 flex items-center gap-2 rounded-lg bg-indigo-600/20 px-3 py-2">
                    <span class="text-2xl">🎉</span>
                    <p class="text-sm font-semibold text-indigo-200">
                        Subiu para o nível {{ (int) ($toast['level'] ?? 1) }}!
                    </p>
                </div>
            @endif

            @foreach (($toast['badges'] ?? []) as $badge)
                <div class="mt-2 flex items-center gap-2 rounded-lg bg-amber-500/15 px-3 py-2">
                    <span class="text-xl">{{ $badge['icon'] ?? '🏅' }}</span>
                    <p class="text-xs font-semibold text-amber-200">
                        Conquista: {{ $badge['label'] ?? '' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
@endif
