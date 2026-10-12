@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">

    <h1 class="mb-6 text-2xl font-bold">Edit Profile</h1>

    <form action="{{ route('profile.update') }}"
          method="POST"
          enctype="multipart/form-data"
          class="space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 shadow-lg">

        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-zinc-300">
                Name
            </label>
            <input type="text"
                   id="name"
                   name="name"
                   value="{{ old('name', $user->name) }}"
                   required
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('name') border-red-500 @else border-zinc-700 @enderror
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">
                Email
            </label>
            <input type="email"
                   id="email"
                   name="email"
                   value="{{ old('email', $user->email) }}"
                   required
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('email') border-red-500 @else border-zinc-700 @enderror
                          focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            @error('email')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Avatar -->
        <div>
            <label for="avatar_image" class="mb-1 block text-sm font-medium text-zinc-300">
                Avatar Image
            </label>
            <div class="mb-2 flex items-center gap-3">
                <img id="avatarPreview"
                     src="{{ $user->avatar_image_path ? asset($user->avatar_image_path) : asset('default-avatar.png') }}"
                     alt="Avatar preview"
                     class="h-16 w-16 rounded-full border border-zinc-700 object-cover">
                <p class="text-xs text-zinc-500">Current avatar — choosing a file below previews the replacement instantly.</p>
            </div>
            <input type="file"
                   id="avatar_image"
                   name="avatar_image"
                   accept="image/*"
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
            @error('avatar_image')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Banner -->
        <div>
            <label for="banner_image" class="mb-1 block text-sm font-medium text-zinc-300">
                Profile Banner <span class="text-zinc-500">(wide image behind your avatar and name)</span>
            </label>
            @if ($user->banner_image_path)
                <img id="bannerPreview" src="{{ asset($user->banner_image_path) }}"
                     alt="Current profile banner"
                     class="mb-2 h-28 w-full rounded-lg border border-zinc-700 object-cover">
                <label class="mb-2 flex cursor-pointer items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox"
                           name="remove_banner"
                           value="1"
                           class="h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-red-600 focus:ring-red-500/40">
                    Remove current banner
                </label>
            @endif
            <img id="bannerPreviewNew"
                     src=""
                     alt="Banner preview"
                     class="mb-2 hidden h-28 w-full rounded-lg border border-zinc-700 object-cover">
            <input type="file"
                   id="banner_image"
                   name="banner_image"
                   accept="image/*"
                   class="block w-full rounded-lg border border-zinc-700 bg-zinc-950 text-sm
                          file:mr-4 file:rounded-md file:border-0
                          file:bg-indigo-600 file:px-4 file:py-2
                          file:text-sm file:font-medium file:text-white
                          hover:file:bg-indigo-500">
            <p class="mt-1 text-xs text-zinc-500">Landscape works best (e.g. 1200×400). Max 4MB — uploading a new one replaces the current banner.</p>
            @error('banner_image')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Bio -->
        <div>
            <label for="bio" class="mb-1 block text-sm font-medium text-zinc-300">
                Bio
            </label>
            <textarea id="bio"
                      name="bio"
                      rows="4"
                      class="w-full rounded-lg border px-3 py-2 text-sm
                             bg-zinc-950
                             @error('bio') border-red-500 @else border-zinc-700 @enderror
                             focus:outline-none focus:ring-2 focus:ring-indigo-500/30">{{ old('bio', $user->bio) }}</textarea>
            @error('bio')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Links -->
        <div id="links-container">
            <label class="mb-2 block text-sm font-medium text-zinc-300">
                Links
            </label>

            <div id="links-list" class="space-y-2">
                @foreach (old('links', $user->links ?? []) as $link)
                    <div class="flex gap-2">
                        <input type="text"
                               name="links[]"
                               value="{{ $link }}"
                               class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
                        <button type="button"
                                class="remove-link rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">
                            Remove
                        </button>
                    </div>
                @endforeach
            </div>

            <button type="button"
                    id="add-link"
                    class="mt-3 inline-flex rounded-lg border border-zinc-700 px-3 py-2 text-sm hover:bg-zinc-800">
                Add Link
            </button>
        </div>

        <!-- Liked posts visibility -->
        <div class="flex items-start gap-3">
            <input type="checkbox"
                   id="show_liked_posts"
                   name="show_liked_posts"
                   value="1"
                   @checked(old('show_liked_posts', $user->show_liked_posts))
                   class="mt-1 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-indigo-600 focus:ring-indigo-500/40">
            <div>
                <label for="show_liked_posts" class="block text-sm font-medium text-zinc-300">
                    Show posts I've liked on my public profile
                </label>
                <p class="mt-0.5 text-xs text-zinc-500">
                    Off by default. Only you can see your liked posts unless this is checked.
                </p>
            </div>
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">
                New Password <span class="text-zinc-500">(leave blank to keep current)</span>
            </label>
            <input type="password"
                   id="password"
                   name="password"
                   class="w-full rounded-lg border px-3 py-2 text-sm
                          bg-zinc-950
                          @error('password') border-red-500 @else border-zinc-700 @enderror">
            @error('password')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password Confirmation -->
        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-zinc-300">
                Confirm New Password
            </label>
            <input type="password"
                   id="password_confirmation"
                   name="password_confirmation"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('profile.show') }}"
               class="inline-flex justify-center rounded-lg border border-zinc-700 px-4 py-2 text-sm hover:bg-zinc-800">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Update Profile
            </button>
        </div>

    </form>
</div>
@endsection
@section('scripts')
<script>
document.getElementById('add-link').addEventListener('click', function () {
    const linksContainer = document.getElementById('links-list');

    const wrapper = document.createElement('div');
    wrapper.className = 'flex gap-2';

    wrapper.innerHTML = `
        <input type="text"
               name="links[]"
               placeholder="Enter link"
               class="flex-1 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm">
        <button type="button"
                class="remove-link rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-500">
            Remove
        </button>
    `;

    linksContainer.appendChild(wrapper);
});

document.getElementById('links-container').addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-link')) {
        e.target.parentElement.remove();
    }
});

// Instant client-side preview for avatar + banner uploads.
function livePreview(inputId, imgId) {
    var input = document.getElementById(inputId);
    var img = document.getElementById(imgId);
    if (!input || !img) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
}
livePreview('avatar_image', 'avatarPreview');
livePreview('banner_image', document.getElementById('bannerPreview') ? 'bannerPreview' : 'bannerPreviewNew');
</script>
@endsection
