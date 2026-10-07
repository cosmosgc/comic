<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What's new (changelogs/*.json, one file per PR)
    |--------------------------------------------------------------------------
    */

    // GitHub repo used by the admin changelog manager to find PRs that
    // don't have a changelog entry yet. Public repos need no token
    // (60 requests/hour); set CHANGELOG_GITHUB_TOKEN to raise the limit.
    'github_repo' => env('CHANGELOG_GITHUB_REPO', 'cosmosgc/comic'),
    'github_token' => env('CHANGELOG_GITHUB_TOKEN', ''),
    'github_per_page' => (int) env('CHANGELOG_GITHUB_PER_PAGE', 30),

    'categories' => ['Added', 'Fixed', 'Changed', 'Removed'],

];
