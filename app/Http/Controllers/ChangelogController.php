<?php

namespace App\Http\Controllers;

use App\Services\ChangelogReader;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function __construct(protected ChangelogReader $reader)
    {
        //
    }

    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $entries = $this->reader->search($this->reader->all(), $query);

        $changelogs = $this->reader->paginate(
            $entries,
            8,
            (int) $request->input('page', 1),
            route('changelog.index'),
            $query !== '' ? ['q' => $query] : []
        );

        return view('changelogs.index', [
            'changelogs' => $changelogs,
            'query' => $query,
        ]);
    }

    public function show(string $entry)
    {
        $changelog = $this->reader->find($entry);
        abort_if($changelog === null, 404);

        $category = $changelog['category'];
        $related = $this->reader->all()
            ->reject(fn ($item) => $item['id'] === $changelog['id'])
            ->filter(fn ($item) => $item['category'] === $category
                || count(array_intersect($item['tags'], $changelog['tags'])) > 0)
            ->take(3)
            ->values();

        return view('changelogs.show', [
            'changelog' => $changelog,
            'related' => $related,
        ]);
    }
}
