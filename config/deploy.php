<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remote host (FTPS) deployment
    |--------------------------------------------------------------------------
    |
    | Used by the admin Deploy panel and the `php artisan deploy:host`
    | command to push code to a host without console access. Credentials
    | live in .env and are never committed — see .env.example.
    |
    */

    'host' => env('FTP_HOST', ''),
    'username' => env('FTP_USERNAME', ''),
    'password' => env('FTP_PASSWORD', ''),
    'port' => (int) env('FTP_PORT', 21),

    // Remote directory that must contain the Laravel project
    // (artisan, composer.json, bootstrap/app.php, ...).
    'root' => env('FTP_ROOT', '/'),

    // Web root inside the project root (checked for index.php).
    'public_dir' => env('FTP_PUBLIC_DIR', 'public'),

    'ssl' => env('FTP_SSL', true),
    'verify_ssl' => env('FTP_SSL_VERIFY', false),
    'timeout' => (int) env('FTP_TIMEOUT', 30),

    // Files/dirs that prove the remote directory is a Laravel project.
    'markers' => [
        'artisan',
        'composer.json',
        'bootstrap/app.php',
        'vendor/autoload.php',
    ],

    // Never uploaded to the remote host. Directory entries ending in `/`
    // exclude the whole subtree; `storage/` skeleton (.gitignore files)
    // is still uploaded so a fresh host gets the required directories,
    // while user uploads and volatile caches are left untouched.
    'excludes' => [
        '.env',
        '.env.backup',
        '.env.production',
        '.git/',
        '.github/',
        '.idea/',
        '.vscode/',
        '.fleet/',
        '.zed/',
        'node_modules/',
        'storage/app/',
        'storage/logs/',
        'storage/framework/cache/',
        'storage/framework/sessions/',
        'storage/framework/views/',
        'storage/framework/testing/',
        'storage/*.key',
        'bootstrap/cache/*.php',
        'public/hot',
        // Trailing slash = whole subtree. ALL user uploads live here
        // (comics pages/covers, avatars, post media) and local files must
        // never overwrite the host's — local and remote differ by design.
        'public/storage/',
        'database/database.sqlite',
        'database/*.sql',
        'tests/',
        'Docs/',
        '_Build.bat',
        '_Install.bat',
        '_Migrate.bat',
        '_start.bat',
        '.phpunit.cache',
        '.phpunit.result.cache',
        'npm-debug.log',
        'yarn-error.log',
        'auth.json',
        'Homestead.json',
        'Homestead.yaml',
    ],

    // Max log lines kept for the admin panel output.
    'max_log_lines' => 300,

];
