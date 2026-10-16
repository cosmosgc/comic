<?php

namespace App\Services;

use App\Models\TagCategory;
use Illuminate\Support\Collection;

/**
 * Assigns tags to categories, Flask-style with EH-namespace rules:
 *
 * - "namespace:name" imports store only the short name ("male:dog boy"
 *   becomes "dog boy"); the namespace picks the category.
 * - Known namespaces map to existing categories ("artist:x" → Artist).
 * - Unknown namespaces (male, female, language, …) auto-create their
 *   own category with random colors on the write path; later tags with
 *   the same namespace append to it.
 * - Plain tags use keyword fallback (exact before substring), then
 *   the "general" category.
 */
class TagCategorizer
{
    /**
     * Split "namespace:name" into parts. Degenerate input (no usable
     * short name, non-alpha namespace like "12:30") is treated as a
     * plain tag so junk never spawns categories.
     *
     * @return array{namespace: string|null, short: string}
     */
    public static function split(string $full): array
    {
        $full = trim($full);

        if (! str_contains($full, ':')) {
            return ['namespace' => null, 'short' => $full];
        }

        [$rawNs, $short] = explode(':', $full, 2);
        $namespace = mb_strtolower(trim($rawNs));
        $short = trim($short);

        if ($short === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $namespace) !== 1) {
            return ['namespace' => null, 'short' => $full];
        }

        return ['namespace' => $namespace, 'short' => $short];
    }

    /**
     * Pure preview of what saving would assign (no rows created).
     * Unknown namespaces come back as an unsaved TagCategory with a
     * random style plus auto_create=true.
     *
     * @return array{name: string, category: TagCategory|null, auto_create: bool}
     */
    public function preview(string $full, ?Collection $categories = null): array
    {
        $categories ??= self::ordered();
        ['namespace' => $namespace, 'short' => $short] = self::split($full);

        if ($namespace !== null) {
            $mapped = self::findByNamespace($namespace, $categories);
            if ($mapped !== null) {
                return ['name' => $short, 'category' => $mapped, 'auto_create' => false];
            }

            return [
                'name' => $short,
                'category' => new TagCategory(array_merge(
                    [
                        'name' => $namespace,
                        'label' => ucfirst($namespace),
                        'namespaces' => [$namespace],
                        'keywords' => [],
                    ],
                    TagCategory::randomStyle()
                )),
                'auto_create' => true,
            ];
        }

        return [
            'name' => $short,
            'category' => self::keywordMatch($short, $categories) ?? $categories->firstWhere('name', 'general'),
            'auto_create' => false,
        ];
    }

    /**
     * Write path: preview, persisting an auto-created namespace
     * category when needed. Namespaced imports also teach their
     * category the short name ("male:dog boy" appends "dog boy" to
     * Male's keywords) so plain re-resolution stays stable; plain
     * imports teach nothing.
     *
     * @return array{name: string, namespace: string|null, category: TagCategory|null, auto_create: bool}
     */
    public function resolveForSaving(string $full): array
    {
        ['namespace' => $namespace] = self::split($full);
        $preview = $this->preview($full);
        $category = $preview['category'];

        if ($category !== null && ! $category->exists) {
            $category = TagCategory::firstOrCreate(
                ['name' => $category->name],
                [
                    'label' => $category->label,
                    'background' => $category->background,
                    'border' => $category->border,
                    'text_color' => $category->text_color,
                    'namespaces' => $category->namespaces,
                    'keywords' => $category->keywords ?? [],
                    'sort_order' => (int) TagCategory::max('sort_order') + 1,
                ]
            );
        }

        if ($category !== null && $namespace !== null) {
            $this->learn($category, $preview['name']);
        }

        $preview['namespace'] = $namespace;
        $preview['category'] = $category;

        return $preview;
    }

    /**
     * Record a short name in its category's learned vocabulary
     * (lowercased, minimum 3 chars to avoid substring noise like
     * "ed"). Learned entries outrank curated keywords, so future
     * plain-text resolution finds the same category.
     */
    public function learn(TagCategory $category, string $short): bool
    {
        $keyword = mb_strtolower(trim($short));
        if (mb_strlen($keyword) < 3) {
            return false;
        }

        $learned = $category->learned_keywords ?? [];
        $lowered = array_map(
            fn ($existing) => mb_strtolower(trim((string) $existing)),
            $learned
        );
        if (in_array($keyword, $lowered, true)) {
            return false;
        }

        $learned[] = $keyword;
        $category->learned_keywords = array_values($learned);
        $category->save();

        return true;
    }

    /**
     * Legacy pure lookup (no creation, no stripping): namespace map,
     * then keyword fallback, then general. Prefer preview()/resolve.
     *
     * @param  Collection<int, TagCategory>|null  $categories
     */
    public function forName(string $name, ?Collection $categories = null): ?TagCategory
    {
        $categories ??= self::ordered();
        if ($categories->isEmpty()) {
            return null;
        }

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        ['namespace' => $namespace] = self::split($name);
        if ($namespace !== null && ($mapped = self::findByNamespace($namespace, $categories)) !== null) {
            return $mapped;
        }

        return self::keywordMatch($name, $categories) ?? $categories->firstWhere('name', 'general');
    }

    /**
     * @return Collection<int, TagCategory>
     */
    public static function ordered(): Collection
    {
        return TagCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, TagCategory>  $categories
     */
    protected static function findByNamespace(string $namespace, Collection $categories): ?TagCategory
    {
        foreach ($categories as $category) {
            foreach ($category->namespaces ?? [] as $mapped) {
                if (mb_strtolower(trim((string) $mapped)) === $namespace) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Keyword fallback: learned vocabulary first (exact, in category
     * order — imports teach their category, so "dog boy" stays Male
     * even though Species also lists it), then curated keywords
     * (exact across every category, then substring in order).
     *
     * @param  Collection<int, TagCategory>  $categories
     */
    protected static function keywordMatch(string $name, Collection $categories): ?TagCategory
    {
        $lowered = mb_strtolower($name);

        foreach ($categories as $category) {
            foreach ($category->learned_keywords ?? [] as $keyword) {
                if (self::keywordEquals($keyword, $lowered)) {
                    return $category;
                }
            }
        }

        foreach ($categories as $category) {
            foreach ($category->keywords ?? [] as $keyword) {
                if (self::keywordEquals($keyword, $lowered)) {
                    return $category;
                }
            }
        }

        foreach ($categories as $category) {
            foreach (array_merge($category->learned_keywords ?? [], $category->keywords ?? []) as $keyword) {
                $keyword = mb_strtolower(trim((string) $keyword));
                if ($keyword !== '' && str_contains($lowered, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    protected static function keywordEquals(mixed $keyword, string $lowered): bool
    {
        $keyword = mb_strtolower(trim((string) $keyword));

        return $keyword !== '' && $lowered === $keyword;
    }
}
