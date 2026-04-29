<?php

namespace App\Console\Commands;

use App\Services\GiftCardService;
use Illuminate\Console\Command;

class ExpireGiftCardsCommand extends Command
{
    protected $signature   = 'gift-cards:expire-due';
    protected $description = 'Sweep expired gift cards and mark them as expired, zeroing out the remaining balance.';

    public function handle(GiftCardService $svc): int
    {
        $n = $svc->expireDue();
        $this->info("Gift cards expired: {$n}");
        return self::SUCCESS;
    }
}
