<?php

namespace App\Models;

use App\Support\ImageStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * An uploaded file attached to a thread (OP) or a single post.
 */
class ForumAttachment extends Model
{
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'user_id',
        'path',
        'original_name',
        'mime',
        'size',
        'image_width',
        'image_height',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        // Attachments live in the real `public/storage` directory (there is
        // no storage symlink on the shared host), so build the URL from the
        // path used by ImageStore instead of the `public` disk.
        return asset('storage/'.$this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * Persist uploaded files against a thread or post. Best-effort:
     * a bad file is skipped rather than failing the whole post.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public static function storeMany(Model $attachable, array $files, int $userId): void
    {
        foreach ($files as $file) {
            try {
                if (! $file->isValid()) {
                    continue;
                }

                // Read size/name before moving the temp file off disk.
                $size = (int) $file->getSize();
                $path = ImageStore::putInStorage($file, 'forum/'.date('Y/m'));

                $width = null;
                $height = null;
                $absolute = public_path('storage/'.$path);
                if (is_file($absolute) && @getimagesize($absolute) !== false) {
                    [$width, $height] = @getimagesize($absolute);
                }

                $attachable->attachments()->create([
                    'user_id' => $userId,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $size,
                    'image_width' => $width,
                    'image_height' => $height,
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
