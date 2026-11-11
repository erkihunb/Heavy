<?php

namespace App\Console\Commands;

use App\Actions\Permits\RemindExpiringPermits;
use Illuminate\Console\Command;

class NotifyExpiringPermitsCommand extends Command
{
    protected $signature = 'permits:notify-expiring';

    protected $description = 'Remind organizations of issued permits that end within the next few days';

    public function handle(RemindExpiringPermits $remind): int
    {
        $count = $remind->handle();

        $this->info("Sent reminders for {$count} permit(s).");

        return self::SUCCESS;
    }
}
