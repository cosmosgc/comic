@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="container">
    <h1>Edit Profile</h1>

    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Name Field -->
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror"
                   id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Email Field -->
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror"
                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Avatar Image Upload Field -->
        <div class="form-group">
            <label for="avatar_image">Avatar Image</label>
            <input type="file" class="form-control @error('avatar_image') is-invalid @enderror"
                   id="avatar_image" name="avatar_image" accept="image/*">
            @error('avatar_image')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Bio Field -->
        <div class="form-group">
            <label for="bio">Bio</label>
            <textarea class="form-control @error('bio') is-invalid @enderror"
                      id="bio" name="bio">{{ old('bio', $user->bio) }}</textarea>
            @error('bio')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Links Field -->
        <div class="form-group" id="links-container">
            <label for="links">Links</label>
            <div id="links-list">
                @foreach (old('links', $user->links ?? []) as $link)
                    <div class="link-item">
                        <input type="text" class="form-control link-input" name="links[]" value="{{ $link }}">
                        <button type="button" class="btn btn-danger remove-link">Remove</button>
                    </div>
                @endforeach
            </div>
            <button type="button" id="add-link" class="btn btn-secondary">Add Link</button>
        </div>

        <!-- Password Field -->
        <div class="form-group">
            <label for="password">New Password (leave blank to keep current password)</label>
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="password" name="password">
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Password Confirmation Field -->
        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" class="form-control"
                   id="password_confirmation" name="password_confirmation">
        </div>

        <button type="submit" class="btn btn-primary">Update Profile</button>
        <a href="{{ route('profile.show') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>

@section('scripts')
<script>
    document.getElementById('add-link').addEventListener('click', function() {
        const linksContainer = document.getElementById('links-list');
        const linkItem = document.createElement('div');
        linkItem.className = 'link-item';
        linkItem.innerHTML = `
            <input type="text" class="form-control link-input" name="links[]" placeholder="Enter link">
            <button type="button" class="btn btn-danger remove-link">Remove</button>
        `;
        linksContainer.appendChild(linkItem);
    });

    document.getElementById('links-container').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-link')) {
            e.target.parentElement.remove();
        }
    });
</script>
@endsection
@endsection

@section('styles')
<style>
    /* Add any custom styles for the edit profile form here */
    .link-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .link-item .link-input {
        flex: 1;
        margin-right: 10px;
    }
</style>
@endsection
