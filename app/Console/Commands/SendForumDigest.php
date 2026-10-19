<?php

namespace App\Console\Commands;

use App\Mail\ForumDigestMail;
use App\Models\ForumThread;
use App\Models\User;
use App\Support\Forum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendForumDigest extends Command
{
    protected $signature = 'forum:digest {--days=7 : Window of recent activity, in days}';

    protected $description = 'Email the opted-in members a digest of recent forum threads';

    public function handle(): int
    {
        if (! Forum::enabled()) {
            $this->info('Forum disabled — no digest sent.');

            return self::SUCCESS;
        }

        if (! Schema::hasColumn('users', 'forum_digest')) {
            $this->info('forum_digest column missing — run the migration first.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $threads = ForumThread::query()
            ->visible()
            ->with('category')
            ->where('last_post_at', '>=', now()->subDays($days))
            ->orderByDesc('last_post_at')
            ->limit(20)
            ->get();

        if ($threads->isEmpty()) {
            $this->info('No recent forum activity.');

            return self::SUCCESS;
        }

        $sent = 0;
        User::query()
            ->where('forum_digest', true)
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use ($threads, &$sent) {
                foreach ($users as $user) {
                    Mail::to($user)->send(new ForumDigestMail($user, $threads));
                    $sent++;
                }
            });

        $this->info("Forum digest sent to {$sent} member(s).");

        return self::SUCCESS;
    }
}
