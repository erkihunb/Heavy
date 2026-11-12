<?php

namespace App\Domain\Permits;

use App\Enums\PermitType;
use App\Models\Branch;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable permit numbers (spec §4.7), e.g. AACRA-2018-000123. The rule
 * is an Admin setting (System settings → Code rules → Permit numbers):
 *
 * - the order of the parts, from {prefix}, {branch} (the code of the branch the
 *   application was made at), {type} (the code of the permit type), {year}
 *   (the Ethiopian year, in four digits or two) and {seq} (the counter);
 * - the prefix, the number of counter digits and the type codes;
 * - whether the counter starts again each Ethiopian year or never, and the
 *   number it starts from.
 *
 * The counter lives in `permit_number_sequences`, one row per Ethiopian year
 * (row 0 when it never restarts). One atomic upsert takes the row lock,
 * increments and returns the number, so concurrent approvals queue up behind
 * each other. Call it inside the approval transaction: if the approval rolls
 * back, so does the increment, and no number is skipped. The counter is shared
 * by all branches and types, so numbers stay unique whatever the rule names.
 */
final class PermitNumberGenerator
{
    /** The counter row used when the counter never restarts. */
    public const int NEVER_RESETS = 0;

    /**
     * The next number for the moment given (its calendar date in the display
     * timezone decides the Ethiopian year).
     */
    public function next(CarbonImmutable $at, ?Branch $branch = null, ?PermitType $type = null): string
    {
        $year = self::year($at);
        $row = self::counterRow($year);
        $start = self::start();

        // One atomic statement: the row is created at the starting number or
        // incremented under its row lock, and LAST_INSERT_ID(expr) hands the new
        // value back to this connection only. No gaps from races, no MAX() + 1.
        DB::statement(
            'INSERT INTO permit_number_sequences (year, last_number) VALUES (?, LAST_INSERT_ID(?)) '.
            'ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
            [$row, $start],
        );

        return self::format((int) DB::scalar('SELECT LAST_INSERT_ID()'), $year, $branch?->code, $type);
    }

    /**
     * The number the next approval would get, without taking it: for the
     * live preview in System settings.
     */
    public function peek(CarbonImmutable $at): int
    {
        $last = DB::table('permit_number_sequences')->where('year', self::counterRow(self::year($at)))->value('last_number');

        return $last === null ? self::start() : (int) $last + 1;
    }

    /**
     * A permit number from its parts, under the current rule.
     */
    public static function format(int $number, int $year, ?string $branchCode = null, ?PermitType $type = null): string
    {
        $permitNumber = strtr((string) config('permits.number_format'), [
            '{prefix}' => (string) config('permits.number_prefix'),
            '{branch}' => (string) $branchCode,
            '{type}' => $type === null ? '' : (string) config("permits.number_type_codes.{$type->value}"),
            '{year}' => config('permits.number_year_format') === 'short'
                ? substr((string) $year, -2)
                : (string) $year,
            '{seq}' => str_pad((string) $number, (int) config('permits.number_digits'), '0', STR_PAD_LEFT),
        ]);

        // A part left empty (an application at the head office) takes its hyphen with it.
        return trim((string) preg_replace('/-{2,}/', '-', $permitNumber), '-');
    }

    public static function year(CarbonImmutable $at): int
    {
        return EthiopianCalendar::toEthiopian($at->setTimezone(config('localization.display_timezone')))->year;
    }

    private static function counterRow(int $year): int
    {
        return config('permits.number_reset') === 'never' ? self::NEVER_RESETS : $year;
    }

    private static function start(): int
    {
        return max(1, (int) config('permits.number_start', 1));
    }
}
