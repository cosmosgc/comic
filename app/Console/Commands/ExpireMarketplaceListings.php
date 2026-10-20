<?php

namespace App\Console\Commands;

use App\Models\MarketplaceListing;
use App\Notifications\MarketplaceNotification;
use Illuminate\Console\Command;

/**
 * Flip active listings past their expiry date to "expired" and tell
 * the seller to renew. Run daily from the scheduler (or manually).
 */
class ExpireMarketplaceListings extends Command
{
    protected $signature = 'marketplace:expire {--dry-run : Only report what would expire}';

    protected $description = 'Expire marketplace listings whose expires_at has passed';

    public function handle(): int
    {
        $query = MarketplaceListing::query()
            ->where('status', MarketplaceListing::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['seller', 'category']);

        if ($this->option('dry-run')) {
            $this->info($query->count().' anúncio(s) expirariam.');

            return self::SUCCESS;
        }

        $expired = 0;
        $query->chunkById(100, function ($listings) use (&$expired): void {
            foreach ($listings as $listing) {
                $listing->update(['status' => MarketplaceListing::STATUS_EXPIRED]);
                $listing->category?->refreshCounters();
                $expired++;

                $listing->seller?->notify(new MarketplaceNotification(
                    'marketplace.expired',
                    'Anúncio expirado',
                    'Seu anúncio "'.$listing->title.'" expirou. Renove para voltar ao Mercado.',
                    route('marketplace.mine'),
                ));
            }
        });

        $this->info($expired.' anúncio(s) expirado(s).');

        return self::SUCCESS;
    }
}
