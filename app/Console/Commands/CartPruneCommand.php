<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;

class CartPruneCommand extends Command
{
    protected $signature = 'cart:prune';

    protected $description = 'Delete expired carts and their items (cascade)';

    public function handle(): int
    {
        // Cart items are deleted automatically via ON DELETE CASCADE on the FK.
        // We chunk to avoid locking the table for a single massive DELETE.
        $pruned = 0;

        Cart::where('expires_at', '<', now())
            ->chunkById(200, function ($carts) use (&$pruned): void {
                $ids = $carts->pluck('id');
                Cart::whereIn('id', $ids)->delete();
                $pruned += $ids->count();
            });

        $this->info("Pruned {$pruned} expired cart(s).");

        return self::SUCCESS;
    }
}
