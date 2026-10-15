<?php

namespace App\Services;

use App\Models\TagCategory;
use Illuminate\Support\Collection;

/**
 * Assigns tags to categories, Flask-style:
 * exact EH-style namespace mapping first ("artist:name" → artist),
 * then keyword fallback (exact match before substring, so
 * "pokemon_(species)" beats the "pokemon" copyright keyword),
 * then the "general" category.
 */
class TagCategorizer
{
    /**
     * @param  Collection<int, TagCategory>|null  $categories
     */
    public function forName(string $name, ?Collection $categories = null): ?TagCategory
    {
        $categories ??= TagCategory::orderBy('sort_order')->orderBy('name')->get();
        if ($categories->isEmpty()) {
            return null;
        }

        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $namespace = null;
        if (str_contains($name, ':')) {
            $namespace = mb_strtolower(trim(explode(':', $name, 2)[0]));
        }

        if ($namespace !== null && $namespace !== '') {
            foreach ($categories as $category) {
                foreach ($category->namespaces ?? [] as $mapped) {
                    if (mb_strtolower(trim((string) $mapped)) === $namespace) {
                        return $category;
                    }
                }
            }
        }

        $lowered = mb_strtolower($name);

        // Exact keyword hits win across every category.
        foreach ($categories as $category) {
            foreach ($category->keywords ?? [] as $keyword) {
                $keyword = mb_strtolower(trim((string) $keyword));
                if ($keyword !== '' && $lowered === $keyword) {
                    return $category;
                }
            }
        }

        // Then substring matches, in category order.
        foreach ($categories as $category) {
            foreach ($category->keywords ?? [] as $keyword) {
                $keyword = mb_strtolower(trim((string) $keyword));
                if ($keyword !== '' && str_contains($lowered, $keyword)) {
                    return $category;
                }
            }
        }

        return $categories->firstWhere('name', 'general');
    }
}
