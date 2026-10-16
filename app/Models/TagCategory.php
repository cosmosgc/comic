<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class TagCategory extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // The cached "general" fallback must follow color edits.
        static::saved(fn () => Cache::forget(self::fallbackKey()));
        static::deleted(fn () => Cache::forget(self::fallbackKey()));
    }

    public static function fallbackKey(): string
    {
        return 'tag-category:general';
    }

    /**
     * The "general" category used whenever a tag has none stored
     * (e.g. tags created before categories existed).
     */
    public static function fallback(): ?self
    {
        return Cache::rememberForever(
            self::fallbackKey(),
            fn () => self::where('name', 'general')->first()
        );
    }

    protected $fillable = [
        'name',
        'label',
        'background',
        'border',
        'text_color',
        'namespaces',
        'keywords',
        'learned_keywords',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'namespaces' => 'array',
            'keywords' => 'array',
            'learned_keywords' => 'array',
            'is_visible' => 'boolean',
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

    /**
     * Random dark-theme-friendly pill style (pastel text on a deep
     * tinted background) for auto-created namespace categories.
     *
     * @return array{background: string, border: string, text_color: string}
     */
    public static function randomStyle(): array
    {
        $hue = random_int(0, 359);

        return [
            'background' => self::hslToHex($hue, 45, 16),
            'border' => self::hslToHex($hue, 65, 48),
            'text_color' => self::hslToHex($hue, 85, 88),
        ];
    }

    protected static function hslToHex(int $h, int $s, int $l): string
    {
        $h = ($h % 360) / 360;
        $s /= 100;
        $l /= 100;

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return sprintf('#%02x%02x%02x',
            self::hueChannel($p, $q, $h + 1 / 3),
            self::hueChannel($p, $q, $h),
            self::hueChannel($p, $q, $h - 1 / 3),
        );
    }

    protected static function hueChannel(float $p, float $q, float $t): int
    {
        $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

        return (int) round(255 * match (true) {
            $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
            $t < 1 / 2 => $q,
            $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
            default => $p,
        });
    }
}
