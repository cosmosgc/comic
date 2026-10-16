<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\EhTagLookupService;
use App\Services\TagCategorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Serialize a category for pill styling (Flask-style bg/border/text).
     *
     * @return array{name: string, label: string, background: string, border: string, text: string}|null
     */
    public static function categoryArray(?TagCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return [
            'name' => $category->name,
            'label' => $category->label,
            'background' => $category->background,
            'border' => $category->border,
            'text' => $category->text_color,
        ];
    }

    /**
     * Autocomplete source for the tag editor: existing tag names
     * matching the query, most-used first.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = Tag::query()->withCount('comics');

        if (! empty($validated['q'])) {
            $query->where('name', 'like', '%'.$validated['q'].'%');
        }

        $tags = $query->orderByDesc('comics_count')
            ->orderBy('name')
            ->limit($validated['limit'] ?? 10)
            ->get(['id', 'name', 'category_id'])
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'category' => self::categoryArray($tag->category),
            ]);

        return response()->json($tags);
    }

    /**
     * External tag lookup (E-Hentai API + title search).
     *
     * Accepts either an EH gallery/page URL or free text (`q`). Fetched
     * tags are compared against local tags (case-insensitive, full
     * "namespace:name" first, then the short name) so the form can
     * auto-apply the ones that already exist.
     */
    public function lookup(Request $request, EhTagLookupService $lookup, TagCategorizer $categorizer): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:500',
            'limit' => 'nullable|integer|min:1|max:5',
        ]);

        if (isset($validated['limit'])) {
            config(['taglookup.lookup_limit' => $validated['limit']]);
        }

        $result = $lookup->lookup($validated['q']);

        $local = Tag::all(['id', 'name', 'category_id']);
        $byName = [];
        foreach ($local as $tag) {
            $byName[mb_strtolower($tag->name)] = $tag;
        }

        $galleries = [];
        $matchedById = [];
        foreach ($result['galleries'] as $gallery) {
            $rawTags = $gallery['tags'] ?? [];
            $normalized = EhTagLookupService::normalizeTags(is_array($rawTags) ? $rawTags : []);
            $tags = [];
            foreach ($normalized as $t) {
                $hit = $byName[mb_strtolower($t['full'])]
                    ?? $byName[mb_strtolower($t['name'])]
                    ?? null;
                if ($hit !== null) {
                    $matchedById[$hit->id] = $hit;
                }
                // Preview category: stored one for known tags, otherwise
                // what saving would assign (short name + namespace
                // category, auto-created with random colors when new).
                $preview = $categorizer->preview($t['full']);
                $tags[] = [
                    'full' => $t['full'],
                    'namespace' => $t['namespace'],
                    'name' => $t['name'],
                    'matched' => $hit !== null,
                    'local' => $hit !== null ? ['id' => $hit->id, 'name' => $hit->name] : null,
                    'category' => self::categoryArray($hit?->category ?? $preview['category']),
                    'auto_create' => $hit === null && $preview['auto_create'],
                    'short_name' => $preview['name'],
                ];
            }

            $galleries[] = [
                'gid' => $gallery['gid'] ?? null,
                'title' => $gallery['title'] ?? null,
                'title_jpn' => $gallery['title_jpn'] ?? null,
                'category' => $gallery['category'] ?? null,
                'rating' => $gallery['rating'] ?? null,
                'thumb' => $gallery['thumb'] ?? null,
                'filecount' => $gallery['filecount'] ?? null,
                'url' => isset($gallery['gid'], $gallery['token'])
                    ? 'https://e-hentai.org/g/'.$gallery['gid'].'/'.$gallery['token'].'/'
                    : null,
                'error' => $gallery['error'] ?? null,
                'tags' => $tags,
            ];
        }

        $matched = array_values(array_map(
            fn ($t) => ['id' => $t->id, 'name' => $t->name],
            $matchedById
        ));

        return response()->json([
            'query' => $validated['q'],
            'source' => $result['source'],
            'galleries' => $galleries,
            'matched' => $matched,
            'matched_names' => array_column($matched, 'name'),
        ]);
    }
}
