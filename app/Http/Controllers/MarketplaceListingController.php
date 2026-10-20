<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMarketplaceListingRequest;
use App\Http\Requests\StoreMarketplaceMessageRequest;
use App\Http\Requests\UpdateMarketplaceListingRequest;
use App\Models\Comic;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceConversation;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingImage;
use App\Models\MarketplaceReport;
use App\Models\MarketplaceTag;
use App\Models\MarketplaceWatch;
use App\Models\UserContactMethod;
use App\Notifications\MarketplaceNotification;
use App\Support\ActivityLogger;
use App\Support\ImageStore;
use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Marketplace write actions: create/edit/delete a listing, lifecycle
 * status, watchlist, reports and the first contact message.
 */
class MarketplaceListingController extends Controller
{
    public function create(Request $request): View
    {
        $this->ensureEnabled();

        return view('marketplace.create', [
            'categories' => $this->categoriesForForm(),
            'comics' => $this->comicsForForm($request),
            'contactMethods' => $request->user()->contactMethods()->get(),
            'maxImages' => Marketplace::maxImages(),
        ]);
    }

    public function store(StoreMarketplaceListingRequest $request): RedirectResponse
    {
        $this->ensureEnabled();
        $data = $request->validated();
        $user = $request->user();
        $isDraft = (bool) ($data['draft'] ?? false);

        $this->guardText($data['title'].' '.$data['description'].' '.($data['tags'] ?? ''));

        if (! $isDraft && Marketplace::activeListingsCount($user) >= Marketplace::maxActiveListingsPerUser()) {
            throw ValidationException::withMessages([
                'title' => 'Você já tem o máximo de '.Marketplace::maxActiveListingsPerUser().' anúncios ativos/rascunhos.',
            ]);
        }

        $listing = DB::transaction(function () use ($data, $user, $isDraft, $request) {
            $now = now();
            $listing = new MarketplaceListing([
                'user_id' => $user->id,
                'marketplace_category_id' => $data['marketplace_category_id'],
                'kind' => $data['kind'],
                'title' => $data['title'],
                'slug' => MarketplaceListing::uniqueSlug($data['title']),
                'description' => $data['description'],
                'condition' => $data['condition'],
                'listing_type' => $data['listing_type'],
                'price' => $data['listing_type'] === 'giveaway' ? null : ($data['price'] ?? null),
                'currency' => Marketplace::defaultCurrency(),
                'price_negotiable' => (bool) ($data['price_negotiable'] ?? false),
                'quantity' => (int) ($data['quantity'] ?? 1),
                'location_city' => $data['location_city'] ?? null,
                'location_region' => $data['location_region'] ?? null,
                'location_country' => $data['location_country'] ?? null,
                'shipping' => $data['shipping'],
                'status' => $isDraft ? MarketplaceListing::STATUS_DRAFT : MarketplaceListing::STATUS_ACTIVE,
                'is_approved' => ! Marketplace::needsApproval(),
                'contact_mode' => $data['contact_mode'],
                'contact_email' => $this->contactEmail($data),
                'published_at' => $isDraft ? null : $now,
                'expires_at' => $isDraft ? null : $now->copy()->addDays(Marketplace::defaultExpiryDays()),
            ]);
            $listing->save();

            $this->storeImages($listing, $request->file('images', []));
            $this->syncComics($listing, $data['comics'] ?? []);
            $this->syncTags($listing, $data['tags'] ?? null);
            $this->syncContactMethods($listing, $data['contact_methods'] ?? []);
            $listing->category?->refreshCounters();

            return $listing;
        });

        ActivityLogger::log('marketplace.listing.created', [
            'user' => $user,
            'subject' => $listing,
            'description' => 'Criou o anúncio "'.$listing->title.'"',
            'metadata' => [
                'listing_id' => $listing->id,
                'kind' => $listing->kind,
                'status' => $listing->status,
                'category_id' => $listing->marketplace_category_id,
            ],
            'ip' => $request->ip(),
        ]);

        $message = $isDraft
            ? 'Rascunho salvo. Publique quando estiver pronto.'
            : ($listing->is_approved
                ? 'Anúncio publicado.'
                : 'Anúncio enviado e aguardando aprovação da moderação.');

        return redirect()->route('marketplace.show', ['listing' => $listing->slug])->with('success', $message);
    }

    public function edit(Request $request, MarketplaceListing $listing): View
    {
        $this->ensureEnabled();
        $this->authorize('update', $listing);

        $listing->load(['images', 'comics', 'tags', 'contactMethods']);

        return view('marketplace.edit', [
            'listing' => $listing,
            'categories' => $this->categoriesForForm(),
            'comics' => $this->comicsForForm($request),
            'contactMethods' => $request->user()->contactMethods()->get(),
            'maxImages' => Marketplace::maxImages(),
        ]);
    }

    public function update(UpdateMarketplaceListingRequest $request, MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('update', $listing);
        $data = $request->validated();
        $isDraft = (bool) ($data['draft'] ?? false);

        $this->guardText($data['title'].' '.$data['description'].' '.($data['tags'] ?? ''));

        DB::transaction(function () use ($listing, $data, $isDraft, $request) {
            $wasDraft = $listing->status === MarketplaceListing::STATUS_DRAFT;
            $status = $listing->status;
            $publishedAt = $listing->published_at;
            $expiresAt = $listing->expires_at;
            $approved = $listing->is_approved;

            if ($isDraft) {
                $status = MarketplaceListing::STATUS_DRAFT;
                $publishedAt = null;
                $expiresAt = null;
            } elseif (in_array($status, [
                MarketplaceListing::STATUS_DRAFT,
                MarketplaceListing::STATUS_HIDDEN,
                MarketplaceListing::STATUS_EXPIRED,
            ], true)) {
                $status = MarketplaceListing::STATUS_ACTIVE;
                $publishedAt ??= now();
                $expiresAt = now()->addDays(Marketplace::defaultExpiryDays());
                if ($wasDraft) {
                    $approved = ! Marketplace::needsApproval();
                }
            }

            $listing->fill([
                'marketplace_category_id' => $data['marketplace_category_id'],
                'kind' => $data['kind'],
                'title' => $data['title'],
                'description' => $data['description'],
                'condition' => $data['condition'],
                'listing_type' => $data['listing_type'],
                'price' => $data['listing_type'] === 'giveaway' ? null : ($data['price'] ?? null),
                'price_negotiable' => (bool) ($data['price_negotiable'] ?? false),
                'quantity' => (int) ($data['quantity'] ?? 1),
                'location_city' => $data['location_city'] ?? null,
                'location_region' => $data['location_region'] ?? null,
                'location_country' => $data['location_country'] ?? null,
                'shipping' => $data['shipping'],
                'status' => $status,
                'is_approved' => $approved,
                'contact_mode' => $data['contact_mode'],
                'contact_email' => $this->contactEmail($data),
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
            ]);
            $listing->save();

            $this->removeImages($listing, $data['remove_images'] ?? []);
            $this->storeImages($listing, $request->file('images', []));
            $this->syncComics($listing, $data['comics'] ?? []);
            $this->syncTags($listing, $data['tags'] ?? null);
            $this->syncContactMethods($listing, $data['contact_methods'] ?? []);
            $listing->category?->refreshCounters();
        });

        return redirect()->route('marketplace.show', ['listing' => $listing->slug])
            ->with('success', 'Anúncio atualizado.');
    }

    public function destroy(MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('delete', $listing);

        $listing->load('images');
        foreach ($listing->images as $image) {
            ImageStore::delete($image->path);
        }
        $listing->delete();
        $listing->category?->refreshCounters();

        return redirect()->route('marketplace.mine')
            ->with('success', 'Anúncio removido.');
    }

    public function updateStatus(Request $request, MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('changeStatus', $listing);

        $validated = $request->validate([
            'action' => 'required|in:active,reserve,sold,hidden,renew,bump',
        ]);

        $action = $validated['action'];

        if ($action === 'bump') {
            $cooldown = Marketplace::bumpCooldownHours();
            if ($listing->bumped_at !== null && $listing->bumped_at->gt(now()->subHours($cooldown))) {
                return back()->with('error', 'Você só pode impulsionar a cada '.$cooldown.' hora(s).');
            }
            if ($listing->status !== MarketplaceListing::STATUS_ACTIVE) {
                return back()->with('error', 'Só é possível impulsionar anúncios ativos.');
            }
            $listing->update(['bumped_at' => now()]);

            return back()->with('success', 'Anúncio impulsionado.');
        }

        $message = match ($action) {
            'reserve' => 'Anúncio marcado como reservado.',
            'sold' => 'Anúncio marcado como vendido.',
            'hidden' => 'Anúncio oculto.',
            'renew' => 'Anúncio renovado por mais '.Marketplace::defaultExpiryDays().' dias.',
            default => 'Anúncio publicado.',
        };

        $attributes = match ($action) {
            'reserve' => ['status' => MarketplaceListing::STATUS_RESERVED],
            'sold' => ['status' => MarketplaceListing::STATUS_SOLD, 'sold_at' => now()],
            'hidden' => ['status' => MarketplaceListing::STATUS_HIDDEN],
            default => [
                'status' => MarketplaceListing::STATUS_ACTIVE,
                'published_at' => $listing->published_at ?? now(),
                'expires_at' => now()->addDays(Marketplace::defaultExpiryDays()),
                'sold_at' => null,
                'is_approved' => $listing->is_approved || ! Marketplace::needsApproval(),
            ],
        };

        $listing->update($attributes);
        $listing->category?->refreshCounters();

        if ($action === 'sold') {
            ActivityLogger::log('marketplace.listing.sold', [
                'user' => $request->user(),
                'subject' => $listing,
                'description' => 'Vendeu "'.$listing->title.'"',
                'metadata' => ['listing_id' => $listing->id],
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function toggleWatch(Request $request, MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('watch', $listing);
        $user = $request->user();

        $watch = MarketplaceWatch::query()
            ->where('user_id', $user->id)
            ->where('marketplace_listing_id', $listing->id)
            ->first();

        if ($watch !== null) {
            $watch->delete();
            $message = 'Removido dos favoritos.';
        } else {
            MarketplaceWatch::create([
                'user_id' => $user->id,
                'marketplace_listing_id' => $listing->id,
            ]);
            $message = 'Adicionado aos favoritos.';
        }

        $listing->update(['watch_count' => $listing->watchers()->count()]);

        return back()->with('success', $message);
    }

    public function report(Request $request, MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('report', $listing);

        $validated = $request->validate([
            'reason' => 'required|in:prohibited,spam,scam,stolen,counterfeit,other',
            'details' => 'nullable|string|max:1000',
        ]);

        $report = MarketplaceReport::create([
            'reporter_id' => $request->user()->id,
            'marketplace_listing_id' => $listing->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
            'status' => 'pending',
        ]);

        ActivityLogger::log('marketplace.reported', [
            'user' => $request->user(),
            'subject' => $listing,
            'description' => 'Denunciou o anúncio "'.$listing->title.'"',
            'metadata' => ['report_id' => $report->id, 'reason' => $report->reason],
            'ip' => $request->ip(),
        ]);

        return back()->with('success', 'Denúncia enviada para a moderação.');
    }

    public function contact(StoreMarketplaceMessageRequest $request, MarketplaceListing $listing): RedirectResponse
    {
        $this->ensureEnabled();
        $this->authorize('contact', $listing);

        $buyer = $request->user();
        $seller = $listing->seller;
        $body = $request->validated()['body'];

        $conversation = DB::transaction(function () use ($listing, $seller, $buyer, $body) {
            $conversation = MarketplaceConversation::firstOrCreate(
                [
                    'marketplace_listing_id' => $listing->id,
                    'buyer_id' => $buyer->id,
                ],
                [
                    'seller_id' => $seller->id,
                    'subject' => $listing->title,
                    'last_message_at' => now(),
                ]
            );
            $created = $conversation->wasRecentlyCreated;

            $conversation->messages()->create([
                'sender_id' => $buyer->id,
                'body' => $body,
            ]);

            $conversation->last_message_at = now();
            $conversation->seller_unread_count = (int) $conversation->seller_unread_count + 1;
            $conversation->save();

            if ($created) {
                $listing->increment('inquiry_count');
            }

            return $conversation;
        });

        ActivityLogger::log('marketplace.inquiry.sent', [
            'user' => $buyer,
            'subject' => $listing,
            'description' => 'Entrou em contato sobre "'.$listing->title.'"',
            'metadata' => ['conversation_id' => $conversation->id],
            'ip' => $request->ip(),
        ]);

        $seller->notify(new MarketplaceNotification(
            'marketplace.inquiry',
            'Novo contato no Mercado',
            $buyer->name.' está interessado em "'.$listing->title.'".',
            route('marketplace.messages.show', ['conversation' => $conversation]),
        ));

        return redirect()->route('marketplace.messages.show', ['conversation' => $conversation])
            ->with('success', 'Mensagem enviada ao vendedor.');
    }

    protected function ensureEnabled(): void
    {
        abort_unless(Marketplace::enabled(), 404);
    }

    /**
     * @return Collection<int, MarketplaceCategory>
     */
    protected function categoriesForForm()
    {
        return MarketplaceCategory::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Comic>
     */
    protected function comicsForForm(Request $request)
    {
        return Comic::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'title', 'slug', 'image_path', 'published_at']);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeImages(MarketplaceListing $listing, array $files): void
    {
        $position = (int) $listing->images()->max('position');
        if ($listing->images()->exists()) {
            $position++;
        }

        foreach (array_slice($files, 0, Marketplace::maxImages()) as $file) {
            $original = $file->getClientOriginalName();
            $mime = $file->getMimeType();
            $size = $file->getSize();
            $dimensions = @getimagesize($file->getRealPath()) ?: null;
            $path = ImageStore::put($file, 'marketplace/listings');

            MarketplaceListingImage::create([
                'marketplace_listing_id' => $listing->id,
                'path' => $path,
                'original_name' => mb_substr((string) $original, 0, 255),
                'mime' => $mime,
                'size' => $size,
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'position' => $position++,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    protected function removeImages(MarketplaceListing $listing, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $images = $listing->images()->whereIn('id', $ids)->get();
        foreach ($images as $image) {
            ImageStore::delete($image->path);
            $image->delete();
        }
    }

    /**
     * @param  array<int, int>  $comicIds
     */
    protected function syncComics(MarketplaceListing $listing, array $comicIds): void
    {
        $sync = [];
        foreach (array_values(array_unique($comicIds)) as $index => $comicId) {
            $sync[(int) $comicId] = ['position' => $index];
        }
        $listing->comics()->sync($sync);
    }

    /**
     * @param  array<int, int>  $ids
     */
    protected function syncContactMethods(MarketplaceListing $listing, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $owned = $ids === []
            ? []
            : UserContactMethod::query()
                ->where('user_id', $listing->user_id)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

        $sync = [];
        foreach ($ids as $id) {
            if (in_array($id, $owned, true)) {
                $sync[$id] = ['sort_order' => count($sync)];
            }
        }

        $listing->contactMethods()->sync($sync);
    }

    protected function syncTags(MarketplaceListing $listing, ?string $raw): void
    {
        $names = collect(explode(',', (string) $raw))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->take(8)
            ->values()
            ->all();

        MarketplaceTag::syncForListing($listing, $names);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function contactEmail(array $data): ?string
    {
        return in_array($data['contact_mode'], ['in_app_and_email', 'email_only'], true)
            ? ($data['contact_email'] ?? null)
            : null;
    }

    protected function guardText(string $text): void
    {
        if ($word = Marketplace::prohibitedWord($text)) {
            throw ValidationException::withMessages([
                'title' => 'O texto contém um termo não permitido ("'.$word.'").',
            ]);
        }
    }
}
