<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['author_id', 'username', 'text', 'media', 'referenced_post_id', 'parent_id'];

    protected $casts = ['media' => 'array'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function referencedPost()
    {
        return $this->belongsTo(Post::class, 'referenced_post_id');
    }

    public function quotes()
    {
        return $this->hasMany(Post::class, 'referenced_post_id');
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }

    public function parent()
    {
        return $this->belongsTo(Post::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Post::class, 'parent_id')->latest();
    }
}
