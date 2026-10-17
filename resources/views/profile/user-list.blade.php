{{-- Paginated user list (followers / following).
     Expects $users (paginator), $emptyMessage (string), $likedPostIds key not needed. --}}
@php($emptyMessage = $emptyMessage ?? 'Ninguém por aqui ainda.')
@if (! $users || $users->isEmpty())
    <p class="text-zinc-500">{{ $emptyMessage }}</p>
@else
    <ul class="divide-y divide-zinc-800 overflow-hidden rounded-2xl border border-zinc-800">
        @foreach ($users as $listUser)
            @php($avatar = $listUser->avatar_image_path ? asset($listUser->avatar_image_path) : asset('default-avatar.png'))
            @php($userUrl = route('profile.public.show.username', ['username' => $listUser->handleName()]))
            <li class="flex items-center gap-3 px-4 py-3">
                <a href="{{ $userUrl }}" data-hovercard="{{ $listUser->name }}">
                    <img src="{{ $avatar }}" alt="{{ $listUser->name }}" class="h-11 w-11 rounded-full border border-zinc-800 object-cover">
                </a>
                <div class="min-w-0 flex-1">
                    <a href="{{ $userUrl }}" data-hovercard="{{ $listUser->name }}" class="block truncate font-bold text-zinc-100 hover:underline">
                        {{ $listUser->name }}
                    </a>
                    <span class="flex items-center gap-2 text-sm text-zinc-500">
                        <span class="truncate">&#64;{{ $listUser->handleName() }}</span>
                        @include('components.user-level', ['user' => $listUser])
                    </span>
                </div>
                @auth
                    @if (! auth()->user()->is($listUser))
                        @php($following = auth()->user()->isFollowing($listUser))
                        <button type="button"
                                onclick="toggleFollow('{{ route('users.follow', $listUser->getKey()) }}', this)"
                                data-following="{{ $following ? '1' : '0' }}"
                                class="rounded-full px-4 py-1.5 text-sm font-bold transition {{ $following ? 'border border-zinc-700 text-zinc-200 hover:border-rose-700 hover:text-rose-300' : 'bg-indigo-600 text-white hover:bg-indigo-500' }}">
                            {{ $following ? 'Seguindo' : 'Seguir' }}
                        </button>
                    @endif
                @endauth
            </li>
        @endforeach
    </ul>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endif
