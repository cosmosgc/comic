<?php

namespace App\Models;

use App\Support\Marketplace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A classified listing. Price is a reference/asking value; the site
 * never handles payment, checkout or escrow.
 */
class MarketplaceListing extends Model
{
    use HasFactory, SoftDeletes;

    public const KIND_COMIC = 'comic';

    public const KIND_PHYSICAL = 'physical';

    public const KIND_DIGITAL = 'digital';

    public const KIND_SERVICE = 'service';

    public const KIND_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'user_id',
        'marketplace_category_id',
        'kind',
        'title',
        'slug',
        'description',
        'condition',
        'listing_type',
        'price',
        'currency',
        'price_negotiable',
        'quantity',
        'location_city',
        'location_region',
        'location_country',
        'shipping',
        'status',
        'is_featured',
        'is_approved',
        'view_count',
        'watch_count',
        'inquiry_count',
        'contact_mode',
        'contact_email',
        'published_at',
        'expires_at',
        'bumped_at',
        'sold_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_negotiable' => 'boolean',
            'quantity' => 'integer',
            'is_featured' => 'boolean',
            'is_approved' => 'boolean',
            'view_count' => 'integer',
            'watch_count' => 'integer',
            'inquiry_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'bumped_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_COMIC => 'Comic do site',
            self::KIND_PHYSICAL => 'Item físico',
            self::KIND_DIGITAL => 'Item digital',
            self::KIND_SERVICE => 'Serviço',
            self::KIND_OTHER => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function conditionOptions(): array
    {
        return [
            'new' => 'Novo',
            'like_new' => 'Como novo',
            'good' => 'Bom',
            'fair' => 'Regular',
            'poor' => 'Com marcas de uso',
            'na' => 'Não se aplica',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'sale' => 'À venda',
            'trade' => 'Troca',
            'giveaway' => 'Doação',
            'wanted' => 'Procuro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function shippingOptions(): array
    {
        return [
            'pickup' => 'Retirada',
            'ships' => 'Envio',
            'digital' => 'Entrega digital',
            'arrange' => 'A combinar',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_ACTIVE => 'Ativo',
            self::STATUS_RESERVED => 'Reservado',
            self::STATUS_SOLD => 'Vendido',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_HIDDEN => 'Oculto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function contactOptions(): array
    {
        return [
            'in_app' => 'Somente chat do site',
            'in_app_and_email' => 'Chat + e-mail',
            'email_only' => 'Somente e-mail',
        ];
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'anuncio';
        }

        $slug = $base;
        $count = 1;
        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(MarketplaceListingImage::class)->orderBy('position')->orderBy('id');
    }

    public function comics(): BelongsToMany
    {
        return $this->belongsToMany(Comic::class, 'marketplace_listing_comics')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceTag::class, 'marketplace_listing_tag')->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'marketplace_watches')->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(MarketplaceConversation::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MarketplaceReport::class);
    }

    /**
     * Approved, active and not past its expiry — what visitors may see.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $inner) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)]);
    }

    public function scopeOfKind(Builder $query, ?string $kind): Builder
    {
        return $kind ? $query->where('kind', $kind) : $query;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_approved
            && $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function isWatchedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->watchers()->whereKey($user->id)->exists();
    }

    public function conversationFor(User $buyer): ?MarketplaceConversation
    {
        return $this->conversations()->where('buyer_id', $buyer->id)->first();
    }

    public function coverUrl(): ?string
    {
        $image = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();
        if ($image !== null) {
            return $image->url();
        }

        $comic = $this->relationLoaded('comics') ? $this->comics->first() : $this->comics()->first();
        if ($comic !== null && $comic->image_path) {
            return asset('storage/'.$comic->image_path);
        }

        return null;
    }

    public function askPrice(): ?float
    {
        return $this->price !== null ? (float) $this->price : null;
    }

    /**
     * Human price line. "A combinar" is used whenever there is no
     * reference price; giveaways/trades read naturally without one.
     */
    public function priceLabel(): string
    {
        if ($this->listing_type === 'giveaway') {
            return 'Grátis';
        }

        if (! Marketplace::showPrices() || $this->price === null) {
            return 'A combinar';
        }

        $formatted = number_format((float) $this->price, 2, ',', '.');

        return Marketplace::currencyLabel($this->currency).' '.$formatted.($this->price_negotiable ? ' (negociável)' : '');
    }

    public function priceShort(): string
    {
        if ($this->listing_type === 'giveaway') {
            return 'Grátis';
        }

        if (! Marketplace::showPrices() || $this->price === null) {
            return 'A combinar';
        }

        return Marketplace::currencyLabel($this->currency).' '.number_format((float) $this->price, 0, ',', '.');
    }

    public function kindLabel(): string
    {
        return static::kindOptions()[$this->kind] ?? $this->kind;
    }

    public function conditionLabel(): string
    {
        return static::conditionOptions()[$this->condition] ?? $this->condition;
    }

    public function typeLabel(): string
    {
        return static::typeOptions()[$this->listing_type] ?? $this->listing_type;
    }

    public function shippingLabel(): string
    {
        return static::shippingOptions()[$this->shipping] ?? $this->shipping;
    }

    public function statusLabel(): string
    {
        return static::statusOptions()[$this->status] ?? $this->status;
    }

    public function contactLabel(): string
    {
        return static::contactOptions()[$this->contact_mode] ?? $this->contact_mode;
    }

    public function locationLabel(): string
    {
        return collect([$this->location_city, $this->location_region, $this->location_country])
            ->filter()
            ->implode(', ');
    }

    public function url(): string
    {
        return route('marketplace.show', ['listing' => $this->slug]);
    }
}
