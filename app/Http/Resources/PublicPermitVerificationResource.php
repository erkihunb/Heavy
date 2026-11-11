<?php

namespace App\Http\Resources;

use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * What anyone who checks a permit may see (spec §7): an explicit whitelist.
 * Never e-mail addresses, phone numbers, owner names, fees, receipts, staff
 * names, notes, internal ids or the token. Built as a plain array because the
 * public page is server-rendered Blade, not an API.
 */
final class PublicPermitVerificationResource
{
    /**
     * @return array{result: string, checked_at: array<string, string>|null, checked_time: string, permit: array<string, mixed>|null}
     */
    public static function make(?Permit $permit, ScanResult $result, string $locale): array
    {
        $now = CarbonImmutable::now(config('localization.display_timezone'));

        return [
            'result' => $result->value,
            'checked_at' => EthiopianCalendar::present($now, $locale),
            'checked_time' => $now->format('H:i'),
            'permit' => $permit === null || $result === ScanResult::NotFound ? null : self::permit($permit, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function permit(Permit $permit, string $locale): array
    {
        $permit->loadMissing(['vehicles.vehicleType', 'organization']);
        $type = $permit->permit_type->value;

        return [
            'permit_number' => $permit->permit_number,
            'permit_type' => $type,
            'vehicles' => $permit->vehicles->map(fn (Vehicle $vehicle): array => [
                'plate' => $vehicle->plate_normalized,
                'role' => $vehicle->roleOnPermit()?->value,
                'vehicle_type' => $vehicle->vehicleType->localizedName($locale),
            ])->values()->all(),
            // Open question §14.4: shown by default.
            'organization' => config('permits.public_shows_organization')
                ? $permit->organization->localizedName($locale)
                : null,
            'valid_from' => EthiopianCalendar::present($permit->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
            'time_window_rules' => config("permits.time_windows.{$type}.{$locale}"),
        ];
    }
}
