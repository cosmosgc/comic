<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Gamification;
use Illuminate\Console\Command;

/**
 * Retro-fit gamification experience onto content that predates the
 * activity feed: every comic, post, reply, comment, like and
 * collection a user already owns is worth exp.
 *
 * The recalculation never subtracts and is idempotent, so it is safe to
 * run repeatedly (e.g. after admins retune the exp table).
 */
class GamificationBackfill extends Command
{
    protected $signature = 'gamification:backfill
        {--dry-run : Only report what would change}
        {--chunk=200 : Users processed per batch}';

    protected $description = 'Award experience for pre-existing content (comics, posts, comments, likes, collections).';

    public function handle(): int
    {
        if (! config('gamification.enabled', true)) {
            $this->error('Gamification is disabled (gamification.enabled = false).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $users = 0;
        $changed = 0;
        $awarded = 0;
        $badges = 0;

        User::query()->chunkById($chunk, function ($batch) use ($dryRun, &$users, &$changed, &$awarded, &$badges) {
            foreach ($batch as $user) {
                $users++;
                $gain = Gamification::backfillPreview($user);

                if ($gain <= 0) {
                    continue;
                }

                $changed++;
                $awarded += $gain;

                if (! $dryRun) {
                    $result = Gamification::recalculate($user);
                    $badges += count($result['badges']);
                } else {
                    // Badges already earned by their current content.
                    $badges += count(Gamification::pendingBadges($user));
                }
            }
        });

        $verb = $dryRun ? 'Would award' : 'Awarded';
        $this->info("Scanned {$users} user(s); {$changed} with content worth XP.");
        $this->info("{$verb} {$awarded} XP across those users".($dryRun ? ' (dry run).' : '.'));
        $this->info(($dryRun ? 'Would unlock' : 'Unlocked')." {$badges} badge(s).");

        return self::SUCCESS;
    }
}
