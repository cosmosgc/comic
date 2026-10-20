<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Support\Marketplace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public, read-mostly marketplace surface: home, browse, category,
 * listing page, search, my listings and the watchlist.
 */
class MarketplaceController extends Controller
{
    /**
     * Shared eager loads for listing cards.
     *
     * @var array<int, string>
     */
    protected const CARD_RELATIONS = [
        'seller:id,name,handle,avatar_image_path,level,created_at',
        'category:id,name,slug,icon',
        'images',
        'comics:id,title,slug,image_path',
    ];

    public function index(Request $request): View
    {
        $this->ensureEnabled();

        $base = MarketplaceListing::query()->visible()->with(self::CARD_RELATIONS);

        $featured = (clone $base)
            ->featured()
            ->orderByDesc('bumped_at')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        $latest = (clone $base)
            ->orderByDesc('bumped_at')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get();

        $endingSoon = (clone $base)
            ->expiringSoon()
            ->orderBy('expires_at')
            ->limit(4)
            ->get();

        $wanted = (clone $base)
            ->where('listing_type', 'wanted')
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $categories = MarketplaceCategory::query()
            ->visible()
            ->withCount(['listings' => fn (Builder $query) => $query->visible()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stats = [
            'active' => MarketplaceListing::query()->visible()->count(),
            'sellers' => MarketplaceListing::query()->visible()->distinct()->count('user_id'),
            'categories' => $categories->count(),
        ];

        return view('marketplace.index', [
            'featured' => $featured,
            'latest' => $latest,
            'endingSoon' => $endingSoon,
            'wanted' => $wanted,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }

    public function browse(Request $request): View
    {
        $this->ensureEnabled();

        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:120',
            'kind' => 'nullable|in:comic,physical,digital,service,other',
            'condition' => 'nullable|in:new,like_new,good,fair,poor,na',
            'type' => 'nullable|in:sale,trade,giveaway,wanted',
            'shipping' => 'nullable|in:pickup,ships,digital,arrange',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'has_comic' => 'nullable|boolean',
            'sort' => 'nullable|in:newest,price_asc,price_desc,watched,ending',
        ]);

        $query = MarketplaceListing::query()
            ->visible()
            ->with(self::CARD_RELATIONS);

        $this->applyFilters($query, $filters);

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderByRaw('price IS NULL')->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'watched' => $query->orderByDesc('watch_count')->orderByDesc('published_at'),
            'ending' => $query->orderBy('expires_at'),
            default => $query->orderByDesc('bumped_at')->orderByDesc('published_at'),
        };

        $listings = $query->paginate(24)->withQueryString();

        return view('marketplace.browse', [
            'listings' => $listings,
            'filters' => $filters,
            'categories' => $this->categoriesForFilters(),
            'searchQuery' => trim((string) ($filters['q'] ?? '')),
            'heading' => 'Explorar o mercado',
        ]);
    }

    public function category(Request $request, string $categorySlug): View
    {
        $this->ensureEnabled();

        $category = MarketplaceCategory::query()
            ->visible()
            ->where('slug', $categorySlug)
            ->firstOrFail();

        $categoryIds = collect([$category->id])
            ->merge($category->children()->visible()->pluck('id'))
            ->all();

        $listings = MarketplaceListing::query()
            ->visible()
            ->whereIn('marketplace_category_id', $categoryIds)
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('is_featured')
            ->orderByDesc('bumped_at')
            ->orderByDesc('published_at')
            ->paginate(24)
            ->withQueryString();

        return view('marketplace.category', [
            'category' => $category,
            'listings' => $listings,
            'children' => $category->children()->visible()->orderBy('sort_order')->get(),
        ]);
    }

    public function search(Request $request): View
    {
        $this->ensureEnabled();

        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $query = trim((string) ($validated['q'] ?? ''));

        if ($query === '') {
            return redirect()->route('marketplace.browse');
        }

        $listings = MarketplaceListing::query()
            ->visible()
            ->with(self::CARD_RELATIONS)
            ->where(function (Builder $inner) use ($query) {
                $inner->where('title', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%')
                    ->orWhereHas('tags', fn (Builder $tag) => $tag->where('name', 'like', '%'.$query.'%'))
                    ->orWhereHas('seller', fn (Builder $seller) => $seller->where('name', 'like', '%'.$query.'%'));
            })
            ->orderByDesc('bumped_at')
            ->orderByDesc('published_at')
            ->paginate(24)
            ->withQueryString();

        return view('marketplace.browse', [
            'listings' => $listings,
            'filters' => [],
            'categories' => $this->categoriesForFilters(),
            'searchQuery' => $query,
            'heading' => 'Resultados para “'.$query.'”',
        ]);
    }

    public function show(Request $request, string $listingSlug): View
    {
        $this->ensureEnabled();

        $listing = MarketplaceListing::query()
            ->where('slug', $listingSlug)
            ->with([
                'seller.badges',
                'category',
                'images',
                'comics' => fn ($query) => $query->withCount('pages'),
                'tags',
                'contactMethods',
            ])
            ->firstOrFail();

        $viewer = $request->user();
        $isOwner = $listing->isOwnedBy($viewer);
        $isAdmin = $viewer !== null && (int) ($viewer->admin_level ?? 0) >= 1;

        abort_unless($listing->isPubliclyVisible() || $isOwner || $isAdmin, 404);

        // Count once per viewer session, like comics and forum threads.
        $viewed = $request->session()->get('viewed_marketplace_listings', []);
        if (! in_array($listing->id, $viewed, true) && ! $isOwner) {
            $listing->increment('view_count');
            $request->session()->push('viewed_marketplace_listings', $listing->id);
        }

        $related = MarketplaceListing::query()
            ->visible()
            ->where('marketplace_category_id', $listing->marketplace_category_id)
            ->whereKeyNot($listing->id)
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('bumped_at')
            ->limit(4)
            ->get();

        $conversation = $viewer !== null && ! $isOwner
            ? $listing->conversationFor($viewer)
            : null;

        return view('marketplace.show', [
            'listing' => $listing,
            'related' => $related,
            'isOwner' => $isOwner,
            'watched' => $listing->isWatchedBy($viewer),
            'conversation' => $conversation,
            'canContact' => $viewer !== null && $viewer->can('contact', $listing),
            'canReport' => $viewer !== null && $viewer->can('report', $listing),
            'hasPrices' => Marketplace::showPrices(),
        ]);
    }

    public function mine(Request $request): View
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'status' => 'nullable|in:all,draft,active,reserved,sold,expired,hidden',
        ]);
        $status = $validated['status'] ?? 'all';
        $user = $request->user();

        $query = $user->marketplaceListings()
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('updated_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $listings = $query->paginate(20)->withQueryString();

        $counts = $user->marketplaceListings()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $counts['all'] = array_sum($counts);

        return view('marketplace.mine', [
            'listings' => $listings,
            'status' => $status,
            'counts' => $counts,
            'limit' => Marketplace::maxActiveListingsPerUser(),
            'activeCount' => $user->activeListingsCount(),
        ]);
    }

    public function watching(Request $request): View
    {
        $this->ensureEnabled();

        $listings = MarketplaceListing::query()
            ->visible()
            ->whereHas('watchers', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('published_at')
            ->paginate(24);

        return view('marketplace.watching', [
            'listings' => $listings,
        ]);
    }

    /**
     * Apply the browse filters to a listing query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search) {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('tags', fn (Builder $tag) => $tag->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('seller', fn (Builder $seller) => $seller->where('name', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }

        foreach (['kind' => 'kind', 'condition' => 'condition', 'shipping' => 'shipping'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['type'])) {
            $query->where('listing_type', $filters['type']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (! empty($filters['has_comic'])) {
            $query->whereHas('comics');
        }
    }

    /**
     * @return Collection<int, MarketplaceCategory>
     */
    protected function categoriesForFilters()
    {
        return MarketplaceCategory::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function ensureEnabled(): void
    {
        abort_unless(Marketplace::enabled(), 404);
    }
}
