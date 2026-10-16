<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receiving side of server-to-server transfers (token-authenticated).
 * Creates the comic shell, accepts page uploads one chunk at a time,
 * then finalizes the cover.
 */
class TransferController extends Controller
{
    /**
     * Create the remote comic shell (metadata + tags, no pages yet).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'slug' => 'nullable|string|max:255',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array|max:2000',
            'tags.*' => 'string|max:255',
        ]);

        $owner = User::where('admin_level', '>=', 1)->orderBy('id')->first()
            ?? User::orderBy('id')->first();
        abort_unless($owner !== null, 422, 'Sem usuários para assumir o comic.');

        $slug = trim((string) ($validated['slug'] ?? ''));
        if ($slug === '' || Comic::where('slug', $slug)->exists()) {
            $slug = Comic::generateUniqueSlug($validated['title']);
        }

        $comic = Comic::create([
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'slug' => $slug,
            'published_at' => $validated['published_at'] ?? null,
            'user_id' => $owner->id,
        ]);

        $tagIds = [];
        foreach ($validated['tags'] ?? [] as $tagName) {
            $tagName = trim($tagName);
            if ($tagName !== '') {
                $tagIds[] = Tag::findOrCreateResolved($tagName)->id;
            }
        }
        $comic->tags()->sync($tagIds);

        return response()->json(['comic_id' => $comic->id]);
    }

    /**
     * Accept one page image for a transferred comic.
     */
    public function storePage(Request $request, Comic $comic): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:'.(config('upload.max_file_mb', 10) * 1024),
            'page_number' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $extension = 'jpg';
        }
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $destination = public_path("storage/comics/{$comic->id}");
        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $number = (int) ($request->input('page_number') ?? 0);
        if ($number < 1) {
            $number = (int) ($comic->pages()->max('page_number') ?? 0) + 1;
        }
        $filename = 'page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.'.$extension;
        $file->move($destination, $filename);

        Page::updateOrCreate(
            ['comic_id' => $comic->id, 'page_number' => $number],
            ['image_path' => "comics/{$comic->id}/{$filename}"]
        );

        return response()->json(['ok' => true, 'page_number' => $number]);
    }

    /**
     * Page numbers already stored remotely (lets the sender push only
     * the missing ones after an interrupted transfer).
     */
    public function pages(Comic $comic): JsonResponse
    {
        return response()->json([
            'pages' => $comic->pages()->orderBy('page_number')->pluck('page_number')->map(fn ($n) => (int) $n)->all(),
        ]);
    }

    /**
     * Point the cover at the first page (when missing) and report back.
     */
    public function finalize(Comic $comic): JsonResponse
    {
        if (($comic->image_path === null || $comic->image_path === '')
            && ($first = $comic->pages()->orderBy('page_number')->first()) !== null) {
            $comic->update(['image_path' => $first->image_path]);
        }

        return response()->json([
            'ok' => true,
            'pages' => $comic->pages()->count(),
            'url' => route('comics.showById', $comic->id),
        ]);
    }
}
