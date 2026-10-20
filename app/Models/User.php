<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Gamification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'handle',
        'email',
        'password',
        'avatar_image_path',
        'banner_image_path',
        'bio',
        'links',
        'admin_level',
        'show_liked_posts',
        'forum_digest',
        'experience',
        'level',
        'last_active_on',
        'current_streak',
        'longest_streak',
        'showcase_badges',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'show_liked_posts' => 'boolean',
            'forum_digest' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_on' => 'date',
            'showcase_badges' => 'array',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function comics(): HasMany
    {
        return $this->hasMany(Comic::class);
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function likedComics(): BelongsToMany
    {
        return $this->belongsToMany(Comic::class, 'comic_user_likes')->withTimestamps();
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class)->latest();
    }

    /**
     * Users this account follows.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'follower_id',
            'followed_id'
        )->withTimestamps();
    }

    /**
     * Users following this account.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'followed_id',
            'follower_id'
        )->withTimestamps();
    }

    public function bookmarkedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bookmarks')->withTimestamps();
    }

    public function marketplaceListings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function marketplaceWatches(): HasMany
    {
        return $this->hasMany(MarketplaceWatch::class);
    }

    public function marketplaceBuyerConversations(): HasMany
    {
        return $this->hasMany(MarketplaceConversation::class, 'buyer_id');
    }

    public function marketplaceSellerConversations(): HasMany
    {
        return $this->hasMany(MarketplaceConversation::class, 'seller_id');
    }

    /**
     * Drafts + active + reserved — the count capped by the marketplace.
     */
    public function activeListingsCount(): int
    {
        return $this->marketplaceListings()
            ->whereIn('status', ['draft', 'active', 'reserved'])
            ->count();
    }

    /**
     * Accounts this user has muted.
     */
    public function mutedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'mutes',
            'user_id',
            'muted_id'
        )->withTimestamps();
    }

    /**
     * Accounts that have muted this user.
     */
    public function mutedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'mutes',
            'muted_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * The stored handle, falling back to a slug of the name for rows
     * created before the handle migration, then a guaranteed non-empty id.
     */
    public function handleName(): string
    {
        $handle = trim((string) ($this->handle ?? ''));
        if ($handle !== '') {
            return $handle;
        }

        $slug = Str::slug((string) $this->name, '');

        return $slug !== '' ? Str::lower($slug) : 'user'.$this->getKey();
    }

    public function isFollowing(User $user): bool
    {
        if ($this->is($user)) {
            return false;
        }

        return $this->following()->whereKey($user->getKey())->exists();
    }

    /**
     * Resolve an @mention token to a user by handle, or by the name with
     * spaces removed (the legacy slug-style mention). Case-insensitive.
     */
    public static function findByMentionToken(string $token): ?self
    {
        $token = ltrim(trim($token), '@');
        if ($token === '') {
            return null;
        }

        return static::query()
            ->where(function ($query) use ($token) {
                $query->where('handle', $token)
                    ->orWhereRaw("LOWER(REPLACE(name, ' ', '')) = ?", [mb_strtolower($token)]);
            })
            ->first();
    }

    /**
     * A unique ^[a-z0-9_]{3,20}$ handle derived from a display name,
     * suffixing a counter on collisions. Used on register and when an
     * admin/backfill needs one without user input.
     */
    public static function generateUniqueHandle(string $name, ?int $ignoreId = null): string
    {
        $base = Str::lower(Str::slug($name, ''));
        $base = preg_replace('/[^a-z0-9_]/', '', (string) $base) ?: 'user';
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }
        $base = substr($base, 0, 20);

        $handle = $base;
        $suffix = 1;
        while (static::query()
            ->where('handle', $handle)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $tail = (string) $suffix;
            $handle = substr($base, 0, 20 - strlen($tail)).$tail;
        }

        return $handle;
    }

    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class, 'user_id')->latest();
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'user_id')->latest();
    }

    public function forumSubscriptions(): HasMany
    {
        return $this->hasMany(ForumSubscription::class, 'user_id');
    }

    public function forumModerations(): HasMany
    {
        return $this->hasMany(ForumModerator::class, 'user_id');
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class)->orderBy('awarded_at');
    }

    public function hasBadge(string $badge): bool
    {
        return $this->badges->contains('badge', $badge);
    }

    /**
     * Badges the user chose to feature, in the chosen order. Falls back
     * to every earned badge when nothing is featured.
     *
     * @return \Illuminate\Support\Collection<int, UserBadge>
     */
    public function featuredBadges(): \Illuminate\Support\Collection
    {
        $badges = $this->relationLoaded('badges') ? $this->badges : $this->badges()->get();
        $selected = is_array($this->showcase_badges) ? $this->showcase_badges : [];

        if ($selected === []) {
            return $badges->values();
        }

        $byKey = $badges->keyBy('badge');

        return collect($selected)
            ->map(fn ($key) => $byKey->get($key))
            ->filter()
            ->values();
    }

    /**
     * The single badge shown next to the author's name in feeds.
     */
    public function topBadge(): ?UserBadge
    {
        return $this->featuredBadges()->first();
    }

    /**
     * Level/experience snapshot for the profile UI.
     *
     * @return array{level: int, exp: int, into_level: int, needed: int, remaining: int, percent: int, next_level: int}
     */
    public function levelProgress(): array
    {
        return Gamification::progress($this);
    }
}
