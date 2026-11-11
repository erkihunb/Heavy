<?php

namespace App\Domain\Permits;

use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable permit numbers, sequential per Ethiopian calendar year
 * (spec §4.7), e.g. TMA-HV-2018-000123.
 *
 * The counter lives in `permit_number_sequences`. One atomic upsert takes the
 * row lock, increments and returns the number, so concurrent approvals queue
 * up behind each other. Call it inside the approval transaction: if the
 * approval rolls back, so does the increment, and no number is skipped.
 */
final class PermitNumberGenerator
{
    /**
     * The next number for the Ethiopian year the given moment falls in
     * (calendar date in the display timezone).
     */
    public function next(CarbonImmutable $at): string
    {
        $year = EthiopianCalendar::toEthiopian(
            $at->setTimezone(config('localization.display_timezone')),
        )->year;

        // One atomic statement: the row for the year is created or incremented
        // under its row lock, and LAST_INSERT_ID(expr) hands the new value back
        // to this connection only. No gaps from races, no MAX() + 1.
        DB::statement(
            'INSERT INTO permit_number_sequences (year, last_number) VALUES (?, LAST_INSERT_ID(1)) '.
            'ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
            [$year],
        );

        $number = (int) DB::scalar('SELECT LAST_INSERT_ID()');

        return sprintf('%s-%d-%06d', config('permits.number_prefix'), $year, $number);
    }
}
