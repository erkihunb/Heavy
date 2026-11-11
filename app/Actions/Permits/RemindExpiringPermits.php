<?php

namespace App\Actions\Permits;

use App\Domain\Notifications\PermitNotifier;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Notifications\PermitExpiringSoon;
use Carbon\CarbonImmutable;

class RemindExpiringPermits
{
    private const int CHUNK = 200;

    public function __construct(private readonly PermitNotifier $notifier) {}

    /**
     * Tell organizations that a permit is about to end (spec §11), once per
     * permit: issued permits whose last day is within the next
     * `permits.notifications.expiring_days` days, today included.
     *
     * @return int how many permits a reminder was sent for
     */
    public function handle(): int
    {
        $today = ValidityRules::today();
        $until = $today->addDays((int) config('permits.notifications.expiring_days'));
        $sent = 0;

        Permit::query()
            ->where('status', PermitStatus::Issued)
            ->whereNull('expiry_reminded_at')
            ->whereBetween('valid_to', [$today->toDateString(), $until->toDateString()])
            ->with('organization')
            ->chunkById(self::CHUNK, function ($permits) use (&$sent): void {
                foreach ($permits as $permit) {
                    // Claim the permit first, so two runs never remind twice.
                    $claimed = Permit::query()
                        ->whereKey($permit->id)
                        ->whereNull('expiry_reminded_at')
                        ->update(['expiry_reminded_at' => CarbonImmutable::now()]);

                    if ($claimed === 1) {
                        $this->notifier->send($permit, new PermitExpiringSoon($permit));
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
