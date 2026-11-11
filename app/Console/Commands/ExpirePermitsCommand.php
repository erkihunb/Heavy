<?php

namespace App\Console\Commands;

use App\Actions\Permits\ExpirePermits;
use Illuminate\Console\Command;

class ExpirePermitsCommand extends Command
{
    protected $signature = 'permits:expire';

    protected $description = 'Mark issued permits whose last day has passed as expired';

    public function handle(ExpirePermits $expirePermits): int
    {
        $count = $expirePermits->handle();

        $this->info("Expired {$count} permit(s).");

        return self::SUCCESS;
    }
}
