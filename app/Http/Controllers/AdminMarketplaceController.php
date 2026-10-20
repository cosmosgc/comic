<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceReport;
use App\Models\MarketplaceSetting;
use App\Notifications\MarketplaceNotification;
use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin → Mercado: dashboard, category tree, listing moderation,
 * the report queue and the settings overlay. Mirrors the forum admin.
 */
class AdminMarketplaceController extends Controller
{
    public function dashboard(): View
    {
        $totals = [
            'active' => MarketplaceListing::visible()->count(),
            'draft' => MarketplaceListing::where('status', MarketplaceListing::STATUS_DRAFT)->count(),
            'sold' => MarketplaceListing::where('status', MarketplaceListing::STATUS_SOLD)->count(),
            'expired' => MarketplaceListing::where('status', MarketplaceListing::STATUS_EXPIRED)->count(),
            'pending' => MarketplaceListing::where('is_approved', false)->count(),
            'reports' => Schema::hasTable('marketplace_reports')
                ? MarketplaceReport::where('status', 'pending')->count()
                : 0,
            'categories' => MarketplaceCategory::count(),
            'sellers' => MarketplaceListing::query()->distinct()->count('user_id'),
        ];

        return view('admin.marketplace.dashboard', [
            'totals' => $totals,
            'latest' => MarketplaceListing::withTrashed()
                ->with(['seller', 'category'])
                ->latest()
                ->limit(10)
                ->get(),
            'reports' => MarketplaceReport::with(['reporter', 'listing' => fn ($query) => $query->withTrashed()])
                ->where('status', 'pending')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function categories(): View
    {
        return view('admin.marketplace.categories', [
            'sections' => MarketplaceCategory::query()
                ->sections()
                ->with(['children' => fn ($query) => $query
                    ->withCount('listings')
                    ->orderBy('sort_order')
                    ->orderBy('name')])
                ->withCount('listings')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'total' => MarketplaceCategory::count(),
        ]);
    }

    public function categoryCreate(): View
    {
        return view('admin.marketplace.category-form', [
            'category' => new MarketplaceCategory(['is_visible' => true, 'sort_order' => 0]),
            'sections' => MarketplaceCategory::sections()->orderBy('sort_order')->get(),
        ]);
    }

    public function categoryStore(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name']);
        $data['is_visible'] = $request->boolean('is_visible');

        MarketplaceCategory::create($data);

        return redirect()->route('admin.marketplace.categories')->with('success', 'Categoria criada.');
    }

    public function categoryEdit(int $id): View
    {
        $category = MarketplaceCategory::findOrFail($id);

        return view('admin.marketplace.category-form', [
            'category' => $category,
            'sections' => MarketplaceCategory::sections()
                ->whereKeyNot($category->id)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function categoryUpdate(Request $request, int $id): RedirectResponse
    {
        $category = MarketplaceCategory::findOrFail($id);
        $data = $this->validateCategory($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $category->id);
        $data['is_visible'] = $request->boolean('is_visible');

        if ($data['parent_id'] !== null && (int) $data['parent_id'] === $category->id) {
            $data['parent_id'] = null;
        }

        // Two levels only: a category with children has to stay a section.
        if ($category->children()->exists()) {
            $data['parent_id'] = null;
        }

        if ($data['parent_id'] !== null) {
            $parent = MarketplaceCategory::find($data['parent_id']);
            if ($parent === null || $parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'Escolha uma seção de primeiro nível.']);
            }
        }

        $category->update($data);

        return redirect()->route('admin.marketplace.categories')->with('success', 'Categoria atualizada.');
    }

    public function categoryDestroy(int $id): RedirectResponse
    {
        $category = MarketplaceCategory::findOrFail($id);

        if ($category->listings()->exists() || $category->children()->exists()) {
            return back()->with('error', 'Mova os anúncios e as subcategorias antes de excluir esta categoria.');
        }

        $category->delete();

        return redirect()->route('admin.marketplace.categories')->with('success', 'Categoria removida.');
    }

    public function categoryToggle(int $id): RedirectResponse
    {
        $category = MarketplaceCategory::findOrFail($id);
        $category->update(['is_visible' => ! $category->is_visible]);

        return back()->with('success', 'Visibilidade atualizada.');
    }

    public function listings(Request $request): View
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|in:all,pending,active,reserved,draft,sold,expired,hidden,removed',
            'category' => 'nullable|integer',
            'kind' => 'nullable|in:comic,physical,digital,service,other',
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $status = $validated['status'] ?? 'all';

        $listings = MarketplaceListing::withTrashed()
            ->with(['seller', 'category'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('title', 'like', '%'.$query.'%')
                        ->orWhereHas('seller', fn ($seller) => $seller->where('name', 'like', '%'.$query.'%'));
                });
            })
            ->when(! empty($validated['category']), fn ($builder) => $builder->where('marketplace_category_id', $validated['category']))
            ->when(! empty($validated['kind']), fn ($builder) => $builder->where('kind', $validated['kind']))
            ->when($status === 'pending', fn ($builder) => $builder->whereNull('deleted_at')->where('is_approved', false))
            ->when($status === 'removed', fn ($builder) => $builder->onlyTrashed())
            ->when(in_array($status, ['active', 'reserved', 'draft', 'sold', 'expired', 'hidden'], true), fn ($builder) => $builder
                ->whereNull('deleted_at')
                ->where('status', $status))
            ->when($status === 'all', fn ($builder) => $builder->whereNull('deleted_at'))
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.marketplace.listings', [
            'listings' => $listings,
            'query' => $query,
            'status' => $status,
            'category' => $validated['category'] ?? null,
            'kind' => $validated['kind'] ?? null,
            'categories' => MarketplaceCategory::orderBy('sort_order')->orderBy('name')->get(),
            'kinds' => MarketplaceListing::kindOptions(),
        ]);
    }

    public function listingAction(Request $request, int $id): RedirectResponse
    {
        $listing = MarketplaceListing::withTrashed()->findOrFail($id);
        $validated = $request->validate([
            'action' => 'required|in:approve,unapprove,feature,unfeature,hide,remove,restore',
        ]);

        $action = $validated['action'];

        switch ($action) {
            case 'approve':
                $listing->update([
                    'is_approved' => true,
                    'status' => in_array($listing->status, [
                        MarketplaceListing::STATUS_DRAFT,
                        MarketplaceListing::STATUS_HIDDEN,
                        MarketplaceListing::STATUS_EXPIRED,
                    ], true) ? MarketplaceListing::STATUS_ACTIVE : $listing->status,
                    'published_at' => $listing->published_at ?? now(),
                    'expires_at' => $listing->expires_at ?? now()->addDays(Marketplace::defaultExpiryDays()),
                ]);
                $listing->seller?->notify(new MarketplaceNotification(
                    'marketplace.approved',
                    'Anúncio aprovado',
                    'Seu anúncio "'.$listing->title.'" foi aprovado e já está no Mercado.',
                    route('marketplace.show', ['listing' => $listing->slug]),
                ));
                $message = 'Anúncio aprovado.';
                break;
            case 'unapprove':
                $listing->update(['is_approved' => false]);
                $message = 'Anúncio despublicado da vitrine.';
                break;
            case 'feature':
                $listing->update(['is_featured' => true]);
                $message = 'Anúncio destacado.';
                break;
            case 'unfeature':
                $listing->update(['is_featured' => false]);
                $message = 'Destaque removido.';
                break;
            case 'hide':
                $listing->update(['status' => MarketplaceListing::STATUS_HIDDEN]);
                $message = 'Anúncio ocultado.';
                break;
            case 'remove':
                $listing->delete();
                $message = 'Anúncio removido.';
                break;
            default:
                $listing->restore();
                $listing->update(['status' => MarketplaceListing::STATUS_HIDDEN, 'is_approved' => false]);
                $message = 'Anúncio restaurado como oculto.';
                break;
        }

        $listing->category?->refreshCounters();

        return back()->with('success', $message);
    }

    public function moderation(): View
    {
        return view('admin.marketplace.moderation', [
            'reports' => MarketplaceReport::with([
                'reporter',
                'reviewer',
                'listing' => fn ($query) => $query->withTrashed()->with(['seller', 'category']),
            ])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(25),
        ]);
    }

    public function resolveReport(Request $request, int $id): RedirectResponse
    {
        $report = MarketplaceReport::findOrFail($id);
        $validated = $request->validate([
            'decision' => 'required|in:reviewed,dismissed',
            'hide_listing' => 'nullable|boolean',
        ]);

        $report->update([
            'status' => $validated['decision'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['decision'] === 'reviewed' && $request->boolean('hide_listing') && $report->listing !== null) {
            $report->listing->update(['status' => MarketplaceListing::STATUS_HIDDEN]);
            $report->listing->category?->refreshCounters();
        }

        $report->reporter?->notify(new MarketplaceNotification(
            'marketplace.report',
            'Denúncia analisada',
            'Sua denúncia sobre "'.$report->listing?->title.'" foi analisada pela moderação.',
            $report->listing !== null ? route('marketplace.show', ['listing' => $report->listing->slug]) : null,
            '🛡️',
        ));

        return back()->with('success', 'Denúncia atualizada.');
    }

    public function settings(): View
    {
        return view('admin.marketplace.settings', [
            'definitions' => MarketplaceSetting::definitions(),
            'values' => collect(MarketplaceSetting::definitions())
                ->mapWithKeys(fn (array $definition, string $key) => [$key => config('marketplace.'.$key)])
                ->all(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $definitions = MarketplaceSetting::definitions();
        $rules = [];

        foreach ($definitions as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'bool' => 'nullable|boolean',
                'int' => 'required|integer|min:1|max:100000',
                'json' => 'nullable|string|max:4000',
                default => $key === 'default_currency'
                    ? 'required|string|size:3'
                    : 'required|string|max:255',
            };
        }

        $request->validate($rules);

        DB::transaction(function () use ($definitions, $request) {
            foreach ($definitions as $key => $definition) {
                if ($definition['type'] === 'bool') {
                    MarketplaceSetting::store($key, $request->boolean($key));
                } elseif ($definition['type'] === 'json') {
                    $words = collect(preg_split('/[\r\n,]+/', (string) $request->input($key)))
                        ->map(fn ($word) => trim($word))
                        ->filter()
                        ->unique(fn ($word) => mb_strtolower($word))
                        ->take(200)
                        ->values()
                        ->all();
                    MarketplaceSetting::store($key, $words);
                } elseif ($key === 'default_currency') {
                    MarketplaceSetting::store($key, strtoupper((string) $request->input($key)));
                } else {
                    MarketplaceSetting::store($key, $request->input($key));
                }
            }
        });

        Marketplace::applySettings();

        return redirect()->route('admin.marketplace.settings')->with('success', 'Configurações do Mercado salvas.');
    }

    /**
     * Run the expiry sweep on demand, for hosts where the scheduler is
     * not wired up. Same command the daily schedule calls.
     */
    public function expireNow(): RedirectResponse
    {
        Artisan::call('marketplace:expire');

        return back()->with('success', trim(Artisan::output()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCategory(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:16',
            'color' => 'nullable|string|max:20',
            'parent_id' => 'nullable|integer|exists:marketplace_categories,id',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_visible' => 'nullable|boolean',
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($data['parent_id'] !== null) {
            $parent = MarketplaceCategory::find($data['parent_id']);
            if ($parent !== null && $parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'A categoria pai precisa ser uma seção de primeiro nível.']);
            }
        }

        return $data;
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'categoria';
        }

        $slug = $base;
        $count = 2;
        while (MarketplaceCategory::where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }
}
