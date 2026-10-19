<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit public JSON contract for a comic.
 *
 * The API used to serialize the Eloquent model directly, which coupled the
 * payload to the table schema. The field list below preserves what the site's
 * scripts consume while keeping internal relations opt-in.
 */
class ComicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'published_at' => $this->published_at,
            'is_exclusive' => (bool) $this->is_exclusive,
            'description' => $this->description,
            'author' => $this->author,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'view_count' => $this->view_count,
            'pages' => PageResource::collection($this->whenLoaded('pages')),
        ];
    }
}
