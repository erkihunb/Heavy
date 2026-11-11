<?php

namespace App\Http\Resources;

use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\PermitReport;
use App\Models\PermitStatusTransition;
use App\Models\VerificationScan;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * What a traffic officer sees after a scan (spec §7): everything the public
 * sees, plus the permit's history, its recent scans and the reports officers
 * filed. Still no fees, receipts or contact details.
 */
final class OfficerPermitVerificationResource
{
    private const int RECENT = 10;

    /**
     * @return array<string, mixed>
     */
    public static function make(?Permit $permit, ScanResult $result, string $locale): array
    {
        $public = PublicPermitVerificationResource::make($permit, $result, $locale);

        if ($public['permit'] === null || $permit === null) {
            return $public;
        }

        $timezone = config('localization.display_timezone');
        $at = fn (\DateTimeInterface $moment): array => [
            'date' => EthiopianCalendar::present(CarbonImmutable::instance($moment)->setTimezone($timezone), $locale),
            'time' => CarbonImmutable::instance($moment)->setTimezone($timezone)->format('H:i'),
        ];

        $public['permit'] += [
            'id' => $permit->id,
            'history' => $permit->transitions()
                ->get()
                ->map(fn (PermitStatusTransition $step): array => [
                    'from' => $step->from_status?->value,
                    'to' => $step->to_status->value,
                    // Revocation and reissue reasons matter at the roadside.
                    'note' => in_array($step->to_status->value, ['revoked', 'issued'], true) ? $step->note : null,
                    ...$at($step->created_at),
                ])
                ->all(),
            'scans' => VerificationScan::query()
                ->where('permit_id', $permit->id)
                ->with('user:id,name')
                ->latest('scanned_at')
                ->limit(self::RECENT)
                ->get()
                ->map(fn (VerificationScan $scan): array => [
                    'result' => $scan->result->value,
                    'channel' => $scan->channel->value,
                    'by' => $scan->user?->name,
                    ...$at($scan->scanned_at),
                ])
                ->all(),
            'reports' => PermitReport::query()
                ->where('permit_id', $permit->id)
                ->with('reporter:id,name')
                ->latest('created_at')
                ->limit(self::RECENT)
                ->get()
                ->map(fn (PermitReport $report): array => [
                    'note' => $report->note,
                    'by' => $report->reporter->name,
                    ...$at($report->created_at),
                ])
                ->all(),
        ];

        return $public;
    }
}
