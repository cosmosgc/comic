<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads the file-per-PR changelog in changelogs/*.json.
 *
 * Entry schema: { pr: int|null, title: string, date: Y-m-d string,
 *   category: string, tags: string[], summary: string, body: string }.
 * Files missing title/date or with invalid JSON are skipped.
 */
class ChangelogReader
{
    public function __construct(protected string $directory)
    {
        //
    }

    public static function fromDefaultPath(): self
    {
        return new self(base_path('changelogs'));
    }

    /**
     * @return Collection<int, array{id: string, pr: int|null, title: string, date: string, category: string, tags: list<string>, summary: string, body: string}>
     */
    public function all(): Collection
    {
        $paths = glob($this->directory.'/*.json') ?: [];

        $entries = collect($paths)
            ->map(fn ($path) => $this->parse($path))
            ->filter()
            ->values();

        return $entries->sortBy([
            ['date', 'desc'],
            ['id', 'desc'],
        ])->values();
    }

    public function find(string $id): ?array
    {
        return $this->all()->firstWhere('id', $id);
    }

    /**
     * PR numbers that already have a changelog entry.
     *
     * @return list<int>
     */
    public function prsUsed(): array
    {
        return $this->all()
            ->map(fn ($entry) => $entry['pr'])
            ->filter(fn ($pr) => $pr !== null)
            ->values()
            ->all();
    }

    /**
     * Case-insensitive match across title, summary, body, category, and tags.
     *
     * @param  Collection<int, array>  $entries
     * @return Collection<int, array>
     */
    public function search(Collection $entries, string $query): Collection
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return $entries;
        }

        return $entries->filter(function (array $entry) use ($query) {
            $haystack = mb_strtolower(implode("\n", [
                $entry['title'],
                $entry['summary'],
                $entry['body'],
                $entry['category'],
                implode(' ', $entry['tags']),
            ]));

            return str_contains($haystack, $query);
        })->values();
    }

    /**
     * @param  Collection<int, array>  $entries
     */
    public function paginate(Collection $entries, int $perPage, int $page, string $path, array $query = []): LengthAwarePaginator
    {
        $page = max(1, $page);
        $total = $entries->count();

        return new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $path, 'query' => $query]
        );
    }

    /** @return array{id: string, pr: int|null, title: string, date: string, category: string, tags: list<string>, summary: string, body: string}|null */
    protected function parse(string $path): ?array
    {
        $decoded = json_decode((string) @file_get_contents($path), true);
        if (! is_array($decoded)) {
            return null;
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $date = trim((string) ($decoded['date'] ?? ''));
        if ($title === '' || ! $this->validDate($date)) {
            return null;
        }

        $tags = $decoded['tags'] ?? [];
        $tags = is_array($tags) ? array_values(array_filter(array_map('strval', $tags))) : [];

        $pr = $decoded['pr'] ?? null;

        return [
            'id' => basename($path, '.json'),
            'pr' => is_numeric($pr) ? (int) $pr : null,
            'title' => $title,
            'date' => $date,
            'category' => trim((string) ($decoded['category'] ?? 'Added')) ?: 'Added',
            'tags' => $tags,
            'summary' => trim((string) ($decoded['summary'] ?? '')),
            'body' => trim((string) ($decoded['body'] ?? '')),
        ];
    }

    protected function validDate(string $date): bool
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);

            return $parsed && $parsed->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }
}
