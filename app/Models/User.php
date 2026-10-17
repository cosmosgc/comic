<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Gamification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'email',
        'password',
        'avatar_image_path',
        'banner_image_path',
        'bio',
        'links',
        'admin_level',
        'show_liked_posts',
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
