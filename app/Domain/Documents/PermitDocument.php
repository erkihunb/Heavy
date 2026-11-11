<?php

namespace App\Domain\Documents;

use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;

/**
 * The HTML of a permit's PDF (spec §8): authority header, permit details in
 * both calendars, the time-window rules and the QR code. Fonts are embedded as
 * data URIs so the document renders the same anywhere, with no network access.
 *
 * Fees, receipts, e-mail addresses, phone numbers and staff names are left
 * out: the paper travels with the driver and is shown at the roadside.
 */
final class PermitDocument
{
    private const array FONTS = [
        'noto-sans-ethiopic-ethiopic-wght-normal.woff2' => 'U+1200-139F, U+2D80-2DDF, U+AB00-AB2F, U+1E7E0-1E7FF',
        'noto-sans-ethiopic-latin-wght-normal.woff2' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2212',
    ];

    public static function html(Permit $permit, string $token): string
    {
        $permit->loadMissing(['vehicles.vehicleType', 'organization']);

        $timezone = config('localization.display_timezone');
        $type = $permit->permit_type->value;

        return view('documents.permit', [
            'permit' => $permit,
            'vehicles' => $permit->vehicles->map(fn (Vehicle $vehicle): array => [
                'plate' => $vehicle->plate_normalized,
                'role_am' => __('permits.vehicle_roles.'.$vehicle->roleOnPermit()?->value, [], 'am'),
                'role_en' => __('permits.vehicle_roles.'.$vehicle->roleOnPermit()?->value, [], 'en'),
                'type_am' => $vehicle->vehicleType->localizedName('am'),
                'type_en' => $vehicle->vehicleType->localizedName('en'),
                'owner' => $vehicle->owner_name,
            ])->all(),
            'organization_am' => $permit->organization->localizedName('am'),
            'organization_en' => $permit->organization->localizedName('en'),
            'type_am' => __("permits.types.{$type}", [], 'am'),
            'type_en' => __("permits.types.{$type}", [], 'en'),
            'valid_from' => EthiopianCalendar::present($permit->valid_from, 'am'),
            'valid_to' => EthiopianCalendar::present($permit->valid_to, 'am'),
            'issued_at' => EthiopianCalendar::present($permit->issued_at?->setTimezone($timezone), 'am'),
            'issued_time' => $permit->issued_at?->setTimezone($timezone)->format('H:i'),
            'rules_am' => config("permits.time_windows.{$type}.am"),
            'rules_en' => config("permits.time_windows.{$type}.en"),
            'qr' => PermitQrCode::svg($token),
            'verify_url' => rtrim((string) config('permits.verify_url'), '/').'/verify',
            'fonts' => self::fontFaces(),
        ])->render();
    }

    /**
     * @return list<array{src: string, range: string}>
     */
    private static function fontFaces(): array
    {
        $faces = [];

        foreach (self::FONTS as $file => $range) {
            $faces[] = [
                'src' => 'data:font/woff2;base64,'.base64_encode((string) file_get_contents(resource_path("fonts/{$file}"))),
                'range' => $range,
            ];
        }

        return $faces;
    }
}
