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

        $row = DB::selectOne(
            'INSERT INTO permit_number_sequences (year, last_number) VALUES (?, 1) '.
            'ON CONFLICT (year) DO UPDATE SET last_number = permit_number_sequences.last_number + 1 '.
            'RETURNING last_number',
            [$year],
        );

        return sprintf('%s-%d-%06d', config('permits.number_prefix'), $year, $row->last_number);
    }
}
