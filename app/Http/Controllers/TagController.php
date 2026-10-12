<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
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
            ->get(['id', 'name']);

        return response()->json($tags);
    }
}
