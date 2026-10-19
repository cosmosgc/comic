<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['author_id', 'text', 'media', 'referenced_post_id', 'parent_id'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function referencedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'referenced_post_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Post::class, 'referenced_post_id');
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }

    public function bookmarkedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Hashtag::class, 'hashtag_post')->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_id')->latest();
    }

    /**
     * Hide posts from authors the viewer muted. Degrades to no-op when the
     * mutes table has not been migrated on the host yet.
     */
    public function scopeNotMutedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null || ! Schema::hasTable('mutes')) {
            return $query;
        }

        return $query->whereNotIn('posts.author_id', function ($sub) use ($user) {
            $sub->select('muted_id')->from('mutes')->where('user_id', $user->id);
        });
    }
}
