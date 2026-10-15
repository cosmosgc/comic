<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'background',
        'border',
        'text_color',
        'namespaces',
        'keywords',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'namespaces' => 'array',
            'keywords' => 'array',
        ];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'category_id');
    }

    /**
     * @return array{background: string, border: string, text: string}
     */
    public function styleArray(): array
    {
        return [
            'background' => $this->background,
            'border' => $this->border,
            'text' => $this->text_color,
        ];
    }
}
