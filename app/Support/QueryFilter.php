<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Reading a list's filters from the query string, the same way on every list.
 */
final class QueryFilter
{
    /**
     * A filter's text, trimmed; empty when it is missing or not a string.
     */
    public static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * A Gregorian `Y-m-d` filter value as the start of that calendar day in
     * the display timezone, or null when it is missing, malformed or not a
     * real day (2026-02-31).
     */
    public static function date(Request $request, string $key): ?CarbonImmutable
    {
        $value = self::text($request, $key);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        return checkdate($month, $day, $year)
            ? CarbonImmutable::create($year, $month, $day, 0, 0, 0, (string) config('localization.display_timezone'))
            : null;
    }
}
