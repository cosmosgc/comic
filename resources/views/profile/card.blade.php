{{-- Shared profile header card with banner background.
     Expects $user. Set $showEdit to show the Edit button (own profile). --}}
@php($bannerUrl = $user->banner_image_path ? asset($user->banner_image_path) : null)
@php($avatarUrl = $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png'))

<div class="mb-8 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-lg">
    {{-- Banner --}}
    <div class="relative h-36 bg-gradient-to-r from-indigo-900 via-zinc-900 to-zinc-900 sm:h-48">
        @if ($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="{{ $user->name }} profile banner"
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 via-zinc-900/20 to-transparent"></div>
        @endif
    </div>

    <div class="flex flex-col items-center px-6 pb-6 text-center">
        <img src="{{ $avatarUrl }}"
             alt="{{ $user->name }} avatar"
             class="relative z-10 -mt-12 h-24 w-24 rounded-full border-4 border-zinc-900 object-cover shadow-lg">

        <h2 class="mt-3 text-xl font-semibold">{{ $user->name }}</h2>

        <p class="mt-2 max-w-xl text-sm text-zinc-400">
            {{ $user->bio ?? 'No bio available' }}
        </p>

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
                <p class="text-sm text-zinc-500">No links added.</p>
            @endif
        </div>

        @if (!empty($showEdit))
            <a href="{{ route('profile.edit') }}"
               class="mt-6 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                Edit profile
            </a>
        @endif
    </div>
</div>
