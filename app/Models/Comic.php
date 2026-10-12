<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Comic extends Model
{
    use HasFactory;

    // Specify the table if necessary (Laravel assumes the plural form of the class name)
    protected $table = 'comics';

    // The fields that are mass assignable
    protected $fillable = [
        'title',
        'description',
        'author',
        'image_path',
        'slug',
        'user_id',
        'view_count',
    ];

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title); // Create the base slug
        $originalSlug = $slug; // Keep the original slug for comparison
        $count = 1;

        // Check if the slug already exists and append a number if needed
        while (Comic::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function pageCount(): int
    {
        return $this->pages()->count();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function collections(): BelongsToMany
    {
        // NOTE: no default order here on purpose (see Collection::comics):
        // orderBy('pivot_order') leaks into exists()/first()/count()
        // subqueries where the alias isn't selected (MySQL 1054).
        return $this->belongsToMany(Collection::class)
            ->withPivot('order'); // Include the 'order' column in the pivot table
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comic_user_likes')->withTimestamps();
    }

    public function setCoverFromPage(Page $page): void
    {
        $this->image_path = $page->image_path;
        $this->save();
    }
}
