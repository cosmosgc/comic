<?php

namespace App\Console\Commands;

use App\Models\Comic;
use Illuminate\Console\Command;

/**
 * Generate slugs for comics created before slugs existed.
 *
 * Replaces the old public `GET /api/comics/update-slugs` endpoint, which
 * let any visitor trigger a mass write (see issues.md #9). Run it from
 * the console or the admin deploy panel instead:
 *
 *     php artisan comics:backfill-slugs --dry-run
 */
class ComicSlugBackfill extends Command
{
    protected $signature = 'comics:backfill-slugs
        {--dry-run : Only report what would change}';

    protected $description = 'Generate unique slugs for comics that have none.';

    public function handle(): int
    {
        $missing = Comic::query()
            ->where(function ($query) {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->get();

        if ($missing->isEmpty()) {
            $this->info('Every comic already has a slug.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        foreach ($missing as $comic) {
            // generateUniqueSlug() runs Str::slug() (so junk/accents are
            // handled) and appends -1, -2… until the slug is free.
            $slug = Comic::generateUniqueSlug((string) $comic->title);

            if ($dryRun) {
                $this->line("#{$comic->id} \"{$comic->title}\" → {$slug}");
                $updated++;

                continue;
            }

            $comic->slug = $slug;
            $comic->save();
            $updated++;
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$verb} {$updated} comic(s).");

        return self::SUCCESS;
    }
}
