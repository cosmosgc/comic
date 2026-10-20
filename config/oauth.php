<?php

/**
 * Social login / account linking (OAuth 2.0).
 *
 * Each provider is optional: when client_id or client_secret is empty the
 * buttons are hidden and the routes answer 404. Register one callback URL
 * per provider at the provider's developer console:
 *
 *   {APP_URL}/auth/{provider}/callback
 *
 * The generic flow lives in App\Services\SocialOAuthService, so adding a
 * provider is only a matter of adding its endpoints and field map here.
 */
return [

    'providers' => [

        'google' => [
            'label' => 'Google',
            'icon' => 'G',
            'button' => 'border border-zinc-700 bg-white text-zinc-900 hover:bg-zinc-100',
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scopes' => ['openid', 'email', 'profile'],
            'pkce' => true,
            'map' => [
                'id' => 'sub',
                'name' => 'name',
                'nickname' => 'given_name',
                'email' => 'email',
                'avatar' => 'picture',
                'email_verified' => 'email_verified',
            ],
        ],

        'discord' => [
            'label' => 'Discord',
            'icon' => '🎮',
            'button' => 'bg-[#5865F2] text-white hover:bg-[#4752C4]',
            'client_id' => env('DISCORD_CLIENT_ID'),
            'client_secret' => env('DISCORD_CLIENT_SECRET'),
            'redirect' => env('DISCORD_REDIRECT_URI'),
            'authorize_url' => 'https://discord.com/oauth2/authorize',
            'token_url' => 'https://discord.com/api/oauth2/token',
            'user_url' => 'https://discord.com/api/users/@me',
            'scopes' => ['identify', 'email'],
            'pkce' => true,
            'map' => [
                'id' => 'id',
                'name' => 'global_name',
                'nickname' => 'username',
                'email' => 'email',
                'avatar' => 'avatar',
                'email_verified' => 'verified',
            ],
        ],

        'github' => [
            'label' => 'GitHub',
            'icon' => '🐙',
            'button' => 'border border-zinc-700 bg-zinc-800 text-white hover:bg-zinc-700',
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect' => env('GITHUB_REDIRECT_URI'),
            'authorize_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_url' => 'https://api.github.com/user',
            'email_url' => 'https://api.github.com/user/emails',
            'scopes' => ['read:user', 'user:email'],
            'pkce' => true,
            'map' => [
                'id' => 'id',
                'name' => 'name',
                'nickname' => 'login',
                'email' => 'email',
                'avatar' => 'avatar_url',
                'email_verified' => null,
            ],
        ],

        'twitter' => [
            'label' => 'X / Twitter',
            'icon' => '𝕏',
            'button' => 'border border-zinc-700 bg-black text-white hover:bg-zinc-900',
            'client_id' => env('TWITTER_CLIENT_ID'),
            'client_secret' => env('TWITTER_CLIENT_SECRET'),
            'redirect' => env('TWITTER_REDIRECT_URI'),
            'authorize_url' => 'https://twitter.com/i/oauth2/authorize',
            'token_url' => 'https://api.twitter.com/2/oauth2/token',
            'user_url' => 'https://api.twitter.com/2/users/me?user.fields=profile_image_url,username',
            'scopes' => ['tweet.read', 'users.read'],
            'pkce' => true,
            'token_auth' => 'basic',
            'map' => [
                'id' => 'data.id',
                'name' => 'data.name',
                'nickname' => 'data.username',
                'email' => null,
                'avatar' => 'data.profile_image_url',
                'email_verified' => null,
            ],
        ],

    ],

];
