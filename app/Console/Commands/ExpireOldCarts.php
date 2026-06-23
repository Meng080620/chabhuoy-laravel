<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;

class ExpireOldCarts extends Command
{
    protected $signature = 'carts:expire {--days=30 : Carts untouched for this many days are cleared}';

    protected $description = 'Delete carts (and their items) that have been abandoned';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        // Delete in chunks so a large backlog doesn't blow memory or hold a
        // long table lock. Cart items cascade via the FK constraint.
        $deleted = 0;

        Cart::where('updated_at', '<', $cutoff)
            ->chunkById(500, function ($carts) use (&$deleted): void {
                foreach ($carts as $cart) {
                    $cart->delete();
                    $deleted++;
                }
            });

        $this->info("Expired {$deleted} abandoned cart(s).");

        return self::SUCCESS;
    }
}
