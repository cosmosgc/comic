<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * File store behind changelogs/*.json (the reader stays read-only).
 */
class ChangelogWriter
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
     * Validate raw input. Returns [validated data, errors].
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function validate(array $input, array $categories): array
    {
        $errors = [];
        $data = [];

        $data['title'] = trim((string) ($input['title'] ?? ''));
        if ($data['title'] === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors['title'] = 'Title must be under 200 characters.';
        }

        $data['date'] = trim((string) ($input['date'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date']) || ! strtotime($data['date'])) {
            $errors['date'] = 'Date must be YYYY-MM-DD.';
        }

        $data['category'] = trim((string) ($input['category'] ?? ''));
        if (! in_array($data['category'], $categories, true)) {
            $errors['category'] = 'Pick a valid category.';
        }

        $pr = trim((string) ($input['pr'] ?? ''));
        if ($pr === '') {
            $data['pr'] = null;
        } elseif (! ctype_digit($pr)) {
            $errors['pr'] = 'PR must be a number.';
        } else {
            $data['pr'] = (int) $pr;
        }

        $tags = trim((string) ($input['tags'] ?? ''));
        $data['tags'] = $tags === ''
            ? []
            : array_values(array_unique(array_filter(array_map(
                fn ($t) => Str::slug(trim($t), '-'),
                preg_split('/[,\n]+/', $tags) ?: []
            ))));

        $data['summary'] = trim((string) ($input['summary'] ?? ''));
        if (mb_strlen($data['summary']) > 500) {
            $errors['summary'] = 'Summary must be under 500 characters.';
        }

        $data['body'] = trim((string) ($input['body'] ?? ''));

        return [$data, $errors];
    }

    /**
     * Suggest a filename id (`YYYY-MM-DD-short-slug`), unique in the dir.
     */
    public function suggestId(string $date, string $title, ?string $ignore = null): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'untitled';
        }
        $base = substr($date.'-'.$slug, 0, 80);
        $id = $base;
        $i = 2;
        while ($id !== $ignore && is_file($this->directory.'/'.$id.'.json')) {
            $id = $base.'-'.$i;
            $i++;
        }

        return $id;
    }

    /**
     * Write (create or overwrite) an entry. Returns the id used.
     *
     * @param  array<string, mixed>  $data  validated data
     */
    public function save(array $data, ?string $id = null): string
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $id ??= $this->suggestId($data['date'], $data['title']);
        // Never allow path traversal via crafted ids.
        $id = (string) Str::of($id)->replaceMatches('/[^a-z0-9\-]/', '');

        file_put_contents(
            $this->directory.'/'.$id.'.json',
            json_encode([
                'pr' => $data['pr'] ?? null,
                'title' => $data['title'],
                'date' => $data['date'],
                'category' => $data['category'],
                'tags' => array_values($data['tags'] ?? []),
                'summary' => $data['summary'] ?? '',
                'body' => $data['body'] ?? '',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        return $id;
    }

    public function delete(string $id): bool
    {
        $path = $this->directory.'/'.basename($id).'.json';
        if (! is_file($path)) {
            return false;
        }

        return @unlink($path);
    }
}
