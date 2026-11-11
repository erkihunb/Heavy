<?php

namespace App\Domain\Reports;

use App\Enums\ExportDataset;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Models\Export;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rows of each export (spec §11), streamed so a large export never sits
 * in memory. Dates in both calendars, money in birr. No e-mail addresses,
 * phone numbers or staff names.
 */
final class ExportDatasets
{
    private const int CHUNK = 500;

    /**
     * @return list<string>
     */
    public function header(Export $export): array
    {
        $columns = match ($export->dataset) {
            ExportDataset::Permits => [
                'permit_number', 'status', 'permit_type', 'organization', 'primary_plate', 'trailer_plates',
                'vehicle_type', 'valid_from_ec', 'valid_from', 'valid_to_ec', 'valid_to', 'days', 'fee_etb',
                'submitted_at', 'issued_at',
            ],
            ExportDataset::Payments => [
                'receipt_number', 'status', 'method', 'amount_etb', 'paid_at_ec', 'paid_at',
                'permit_number', 'organization', 'decided_at',
            ],
        };

        return array_map(fn (string $column): string => __("reports.exports.columns.{$column}"), $columns);
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(Export $export): Generator
    {
        $rows = match ($export->dataset) {
            ExportDataset::Permits => $this->permits($export),
            ExportDataset::Payments => $this->payments($export),
        };

        foreach ($rows as $row) {
            yield array_map(self::cell(...), $row);
        }
    }

    /**
     * Text a spreadsheet program would run as a formula (it starts with =, +,
     * -, @, a tab or a carriage return) gets a leading apostrophe, so that it
     * is shown as text (OWASP "CSV injection"). Organisation names and receipt
     * numbers are typed by applicants.
     */
    public static function cell(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    private function permits(Export $export): Generator
    {
        $filters = $export->filters ?? [];
        $status = PermitStatus::tryFrom((string) ($filters['status'] ?? ''));
        $now = CarbonImmutable::now();
        $timezone = (string) config('localization.display_timezone');

        $query = Permit::query()
            ->with(['organization:id,name_am,name_en', 'vehicles.vehicleType'])
            // Drafts and withdrawn applications are the applicant's business only.
            ->whereNotIn('status', [PermitStatus::Draft, PermitStatus::Withdrawn])
            ->when($status !== null, fn (Builder $query) => $query->inEffectiveStatus($status))
            ->when(isset($filters['from']), fn (Builder $query) => $query->where('valid_to', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->where('valid_from', '<=', $filters['to']))
            ->orderBy('id');

        foreach ($query->lazy(self::CHUNK) as $permit) {
            $primary = $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit()?->value === 'primary');
            $trailers = $permit->vehicles->reject(fn (Vehicle $vehicle): bool => $vehicle === $primary);

            yield [
                $permit->permit_number,
                __("permits.status.{$permit->effectiveStatus($now)->value}"),
                __("permits.types.{$permit->permit_type->value}"),
                $permit->organization->localizedName(app()->getLocale()),
                $primary?->plate_normalized,
                $trailers->pluck('plate_normalized')->implode(' / '),
                $primary?->vehicleType->localizedName(app()->getLocale()),
                EthiopianCalendar::format($permit->valid_from),
                $permit->valid_from->toDateString(),
                EthiopianCalendar::format($permit->valid_to),
                $permit->valid_to->toDateString(),
                $permit->durationDays(),
                $permit->fee_cents === null ? null : $permit->fee_cents / 100,
                $permit->submitted_at?->setTimezone($timezone)->format('Y-m-d H:i'),
                $permit->issued_at?->setTimezone($timezone)->format('Y-m-d H:i'),
            ];
        }
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    private function payments(Export $export): Generator
    {
        $filters = $export->filters ?? [];
        $status = PaymentStatus::tryFrom((string) ($filters['status'] ?? ''));
        $timezone = (string) config('localization.display_timezone');

        $query = Payment::query()
            ->with(['permit:id,permit_number,organization_id', 'permit.organization:id,name_am,name_en'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when(isset($filters['from']), fn (Builder $query) => $query->where('paid_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->where('paid_at', '<=', $filters['to']))
            ->orderBy('id');

        foreach ($query->lazy(self::CHUNK) as $payment) {
            yield [
                $payment->receipt_number,
                __("payments.status.{$payment->status->value}"),
                __("payments.methods.{$payment->method->value}"),
                $payment->amount_cents / 100,
                EthiopianCalendar::format($payment->paid_at),
                $payment->paid_at->toDateString(),
                $payment->permit->permit_number,
                $payment->permit->organization->localizedName(app()->getLocale()),
                $payment->verified_at?->setTimezone($timezone)->format('Y-m-d H:i'),
            ];
        }
    }
}
