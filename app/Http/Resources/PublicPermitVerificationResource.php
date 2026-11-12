<?php

namespace App\Http\Resources;

use App\Domain\Permits\MovementHours;
use App\Enums\PaymentStatus;
use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * What anyone who checks a permit may see (spec §7): an explicit whitelist.
 * The details printed on the permit itself are shown too (owner's request,
 * 2026-11-12): vehicle owners, the reason, the receipt number, the issuer,
 * the branch and the issue date. Never e-mail addresses, phone numbers, fees,
 * notes, internal ids or the token. Built as a plain array because the public
 * page is server-rendered Blade, not an API.
 */
final class PublicPermitVerificationResource
{
    /**
     * @return array{result: string, checked_at: array<string, string>|null, checked_time: string, checked_time_ethiopian: string, permit: array<string, mixed>|null}
     */
    public static function make(?Permit $permit, ScanResult $result, string $locale): array
    {
        $now = CarbonImmutable::now(config('localization.display_timezone'));

        return [
            'result' => $result->value,
            'checked_at' => EthiopianCalendar::present($now, $locale),
            'checked_time' => $now->format('H:i'),
            // The time of the check on the Ethiopian clock too ("ከቀኑ 9:30").
            'checked_time_ethiopian' => __('permits.hours.periods.'.MovementHours::ethiopian($now->format('H:i'))['period'], [
                'time' => MovementHours::ethiopian($now->format('H:i'))['hour'],
            ], $locale),
            'permit' => $permit === null || $result === ScanResult::NotFound ? null : self::permit($permit, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function permit(Permit $permit, string $locale): array
    {
        $permit->loadMissing(['vehicles.vehicleType', 'organization', 'branch', 'approver', 'payments']);
        $type = $permit->permit_type->value;
        $issuedAt = $permit->issued_at?->setTimezone(config('localization.display_timezone'));
        // The receipt the fee was paid with, as on the permit: the verified one, else the latest.
        $payment = $permit->payments->firstWhere('status', PaymentStatus::Verified) ?? $permit->payments->last();

        return [
            'permit_number' => $permit->permit_number,
            'permit_type' => $type,
            'vehicles' => $permit->vehicles->map(fn (Vehicle $vehicle): array => [
                'plate' => $vehicle->plate_normalized,
                'role' => $vehicle->roleOnPermit()?->value,
                'vehicle_type' => $vehicle->vehicleType->localizedName($locale),
                'owner' => $vehicle->owner_name,
            ])->values()->all(),
            'reason' => $permit->reason,
            'issued_at' => EthiopianCalendar::present($issuedAt, $locale),
            'issued_time' => $issuedAt?->format('H:i'),
            'branch' => $permit->branch?->localizedName($locale),
            'issuer' => $permit->approver?->name,
            'receipt_number' => $payment?->receipt_number,
            // As printed on its PDF; compared with the paper to catch an altered copy.
            'certificate_code' => $permit->certificate_code,
            // Open question §14.4: shown by default.
            'organization' => config('permits.public_shows_organization')
                ? $permit->organization->localizedName($locale)
                : null,
            'valid_from' => EthiopianCalendar::present($permit->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
            'time_window_rules' => config("permits.time_windows.{$type}.{$locale}"),
            // The hours of the day it lets the vehicles move; null for any time of day.
            'hours' => $permit->movementHours()?->present($locale),
        ];
    }
}
