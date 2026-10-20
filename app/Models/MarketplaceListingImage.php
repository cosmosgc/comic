<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceListingImage extends Model
{
    protected $fillable = [
        'marketplace_listing_id',
        'path',
        'original_name',
        'mime',
        'size',
        'width',
        'height',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function url(): string
    {
        return asset(str_starts_with($this->path, 'storage/') ? $this->path : 'storage/'.$this->path);
    }
}
