<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A forum section (parent_id = null) or board (child of a section).
 * Two levels only, in one self-referential table.
 */
class ForumCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'banner_image_path',
        'banner_position_x',
        'banner_position_y',
        'sort_order',
        'is_visible',
        'is_locked',
        'min_role',
        'thread_count',
        'post_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_locked' => 'boolean',
            'banner_position_x' => 'integer',
            'banner_position_y' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function isSection(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Public URL of the board banner, or null when none was uploaded.
     */
    public function bannerUrl(): ?string
    {
        return $this->banner_image_path ? asset($this->banner_image_path) : null;
    }

    /**
     * CSS object-position for the banner focal point (e.g. "50% 50%").
     */
    public function bannerPosition(): string
    {
        return max(0, min(100, (int) ($this->banner_position_x ?? 50))).'% '
            .max(0, min(100, (int) ($this->banner_position_y ?? 50))).'%';
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeSections(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeBoards(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            'guest' => 'Todos (visitantes)',
            'member' => 'Membros',
            'moderator' => 'Moderadores',
            'admin' => 'Administradores',
        ];
    }

    /**
     * Minimum role level required to start threads here.
     */
    public function minRoleLevel(): int
    {
        return match ((string) $this->min_role) {
            'guest' => 0,
            'member' => 1,
            'moderator' => 2,
            'admin' => 3,
            default => 1,
        };
    }

    public function refreshCounters(): void
    {
        $this->thread_count = $this->threads()->count();
        $this->post_count = ForumPost::query()
            ->whereIn('forum_thread_id', $this->threads()->select('id'))
            ->count();
        $this->save();
    }
}
