<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Lists pull requests from GitHub so the admin changelog manager can spot
 * PRs that don't have a changelogs/*.json entry yet.
 */
class GithubPullRequests
{
    /**
     * @return list<array{number: int, title: string, body: string, state: string, merged: bool, author: string, url: string, created_at: string}>
     *
     * @throws \RuntimeException on network/API failure
     */
    public function fetch(string $repo, ?string $token = null, int $perPage = 30): array
    {
        $request = Http::timeout(20)->withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'comic-changelog-manager',
        ]);
        if ($token) {
            $request = $request->withToken($token);
        }

        $response = $request->get(
            "https://api.github.com/repos/{$repo}/pulls",
            ['state' => 'all', 'sort' => 'updated', 'direction' => 'desc', 'per_page' => max(1, min(100, $perPage))]
        );

        if ($response->failed()) {
            $message = 'GitHub API error '.$response->status();
            $apiMessage = $response->json('message');
            if (is_string($apiMessage) && $apiMessage !== '') {
                $message .= ': '.$apiMessage;
            }
            throw new \RuntimeException($message.'. '.($response->status() === 403
                ? 'Rate-limited? Set CHANGELOG_GITHUB_TOKEN.'
                : 'Check CHANGELOG_GITHUB_REPO.'));
        }

        return collect($response->json() ?? [])
            ->map(fn ($pr) => [
                'number' => (int) ($pr['number'] ?? 0),
                'title' => (string) ($pr['title'] ?? '(no title)'),
                'body' => (string) ($pr['body'] ?? ''),
                'state' => (string) ($pr['state'] ?? 'open'),
                'merged' => (bool) ($pr['merged_at'] ?? false),
                'author' => (string) ($pr['user']['login'] ?? '?'),
                'url' => (string) ($pr['html_url'] ?? ''),
                'created_at' => substr((string) ($pr['created_at'] ?? ''), 0, 10),
            ])
            ->filter(fn ($pr) => $pr['number'] > 0)
            ->values()
            ->all();
    }
}
