<?php

namespace App\Actions\Permits;

use App\Domain\Permits\PermitTransitions;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Jobs\StampPermitDocument;
use App\Models\Permit;

class ExpirePermits
{
    private const int CHUNK = 200;

    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * Mark issued permits whose last day has passed as expired (spec §4.9).
     * Verification already treats them as expired by date; storing it keeps
     * lists and reports simple and frees the vehicles for other permits.
     *
     * One short transaction per permit, so a large backlog never holds long
     * locks, and a permit revoked meanwhile is simply skipped. Safe to run
     * more than once a day.
     *
     * @return int how many permits were expired
     */
    public function handle(): int
    {
        $today = ValidityRules::today()->toDateString();
        $expired = 0;

        Permit::query()
            ->where('status', PermitStatus::Issued)
            ->where('valid_to', '<', $today)
            ->orderBy('id')
            ->select('id')
            ->chunkById(self::CHUNK, function ($permits) use (&$expired): void {
                foreach ($permits as $permit) {
                    try {
                        // No actor: the system expires the permit.
                        $this->transitions->apply($permit, PermitStatus::Expired, null);
                        $expired++;

                        // Print its PDFs again, stamped "expired" in both languages.
                        StampPermitDocument::dispatch($permit->id)->afterCommit();
                    } catch (InvalidPermitTransition) {
                        // Changed (e.g. revoked) since it was selected: nothing to do.
                    }
                }
            });

        return $expired;
    }
}
