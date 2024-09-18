<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    // If you need to cast any attributes to specific data types
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function generateUniqueSlug($title)
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
