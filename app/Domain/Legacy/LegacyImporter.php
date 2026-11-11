<?php

namespace App\Domain\Legacy;

use App\Domain\Permits\PermitNumberGenerator;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\ImportStatus;
use App\Enums\IssueSeverity;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\VehicleRole;
use App\Models\ImportBatch;
use App\Models\ImportIssue;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;
use Throwable;

/**
 * Imports the CSV of the legacy Google Form, "Form Responses 1" (spec §13).
 *
 * - Streams the file; rows are imported in chunks, one transaction per chunk
 *   and a savepoint per row, so memory stays flat and one bad row never
 *   loses the others.
 * - Idempotent: a row whose `legacy_source_ref` already exists is counted as
 *   already imported and left alone.
 * - Every value that cannot be read cleanly becomes an issue: the row is
 *   imported with a warning where that is safe, and skipped where it is not
 *   (no readable plate, permit type, validity or organization).
 * - A dry run does all of the above inside one transaction that is rolled
 *   back, so only the batch and its issues are kept.
 */
final class LegacyImporter
{
    public const int CHUNK = 200;

    /** Column positions in "Form Responses 1" (spec §1). */
    private const int TIMESTAMP = 0;

    private const int EMAIL = 1;

    private const int OWNER = 2;

    private const int ORGANIZATION = 3;

    private const int PERMIT_TYPE = 4;

    private const int VEHICLE_TYPE = 5;

    private const int PLATES = 6;

    private const int REASON = 7;

    private const int VALIDITY = 8;

    private const int AMOUNT = 9;

    private const int RECEIPT = 10;

    /** The minimum number of columns a row needs (up to the receipt number). */
    private const int COLUMNS = 11;

    public const string SYSTEM_USER_EMAIL = 'legacy-import@system.invalid';

    public const string UNMAPPED_TYPE = 'legacy_unmapped';

    /** @var list<string> */
    public const array DEFAULT_TIMESTAMP_FORMATS = ['n/j/Y G:i:s', 'n/j/Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'n/j/Y G:i'];

    private ImportBatch $batch;

    private User $systemUser;

    /** @var array<string, string> vehicle type spelling → code */
    private array $typeMap;

    /** @var array<string, int> vehicle type code → id */
    private array $typeIds = [];

    /** @var array<string, int> normalized organization name → id */
    private array $organizations = [];

    /** @var array<string, array{id: string, organization_id: int}> plate → vehicle */
    private array $vehicles = [];

    /** @var list<string> */
    private array $timestampFormats;

    /** @var array<string, int> */
    private array $totals;

    /** @var list<array{source_row: int, field: string, raw_value: string|null, message: string, severity: string}> */
    private array $issues = [];

    /** @var list<array{source_row: int, field: string, raw_value: string|null, message: string, severity: string}> */
    private array $rowIssues = [];

    /** @var array{organizations: list<string>, vehicles: list<string>, types: list<string>} cache entries added by the current row */
    private array $rowCache = ['organizations' => [], 'vehicles' => [], 'types' => []];

    public function __construct(private readonly PermitNumberGenerator $numbers) {}

    /**
     * @param  list<string>|null  $timestampFormats  PHP date formats of the Timestamp column
     * @return array<string, int> the batch totals
     *
     * @throws Throwable when the file cannot be read or the database fails; the batch is marked failed
     */
    public function run(ImportBatch $batch, string $path, ?array $timestampFormats = null): array
    {
        $this->batch = $batch;
        $this->timestampFormats = $timestampFormats ?: self::DEFAULT_TIMESTAMP_FORMATS;
        $this->typeMap = require database_path('import/vehicle_type_map.php');
        $this->totals = array_fill_keys([
            'rows', 'imported', 'already_imported', 'skipped', 'warnings',
            'organizations_created', 'vehicles_created', 'payments_created', 'not_blocking',
        ], 0);

        $batch->forceFill(['status' => ImportStatus::Running, 'started_at' => CarbonImmutable::now(), 'error' => null])->save();

        try {
            $this->systemUser = $this->systemUser();
            $this->loadCaches();

            if ($batch->dry_run) {
                DB::beginTransaction();

                try {
                    $this->processFile($path);
                } finally {
                    DB::rollBack();
                }
            } else {
                $this->processFile($path);
            }

            $this->saveIssues();
            $this->writeIssuesCsv();

            $batch->forceFill([
                'status' => ImportStatus::Completed,
                'totals' => $this->totals,
                'finished_at' => CarbonImmutable::now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->saveIssues();

            $batch->forceFill([
                'status' => ImportStatus::Failed,
                'totals' => $this->totals,
                'finished_at' => CarbonImmutable::now(),
                'error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            throw $exception;
        }

        return $this->totals;
    }

    private function processFile(string $path): void
    {
        if (! is_readable($path)) {
            throw new RuntimeException("The file [{$path}] cannot be read.");
        }

        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $chunk = [];
        $line = 0;

        foreach ($file as $cells) {
            $line++;

            if ($line === 1 || ! is_array($cells) || $cells === [null]) {
                continue; // The header row.
            }

            $chunk[] = [$line, array_map(fn ($cell): string => (string) $cell, $cells)];

            if (count($chunk) === self::CHUNK) {
                $this->processChunk($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($chunk);
        }
    }

    /**
     * @param  list<array{0: int, 1: list<string>}>  $rows
     */
    private function processChunk(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as [$line, $cells]) {
                $this->totals['rows']++;
                $this->rowIssues = [];
                $this->rowCache = ['organizations' => [], 'vehicles' => [], 'types' => []];

                try {
                    DB::transaction(fn () => $this->importRow($line, $cells));
                    $this->issues = [...$this->issues, ...$this->rowIssues];
                } catch (RowSkipped $skipped) {
                    $this->forgetRowCache();
                    $this->totals['skipped']++;
                    $this->issues[] = $this->issue($line, $skipped->field, $skipped->rawValue, $skipped->getMessage(), IssueSeverity::Skipped);
                } catch (QueryException $exception) {
                    $this->forgetRowCache();
                    $this->totals['skipped']++;
                    $this->issues[] = $this->issue($line, 'row', null, 'The database refused the row ('.$exception->getCode().').', IssueSeverity::Skipped);
                }
            }
        });
    }

    /**
     * @param  list<string>  $cells
     *
     * @throws RowSkipped
     */
    private function importRow(int $line, array $cells): void
    {
        if (count($cells) < self::COLUMNS) {
            throw new RowSkipped('row', implode(',', $cells), 'The row has '.count($cells).' columns; at least '.self::COLUMNS.' are expected.');
        }

        $cell = fn (int $column): string => trim($cells[$column] ?? '');

        $reference = LegacyValues::sourceRef($cell(self::TIMESTAMP), $cell(self::EMAIL), $cell(self::PLATES));

        if (Permit::query()->where('legacy_source_ref', $reference)->exists()) {
            $this->totals['already_imported']++;

            return;
        }

        // Values without which the row is not a permit: skip the row.
        $permitType = $this->required($line, 'permit_type', $cell(self::PERMIT_TYPE), fn (string $raw) => LegacyValues::permitType($raw));
        [$validFrom, $validTo] = $this->required($line, 'validity', $cell(self::VALIDITY), fn (string $raw) => LegacyValues::validity($raw));
        $plates = $this->plates($line, $cell(self::PLATES));
        $organization = LegacyValues::organization($cell(self::ORGANIZATION));

        if ($organization['key'] === '') {
            throw new RowSkipped('organization', $cell(self::ORGANIZATION), 'Organization name is empty.');
        }

        // Values the row can do without: import with a warning.
        $submittedAt = $this->optional($line, 'timestamp', $cell(self::TIMESTAMP), fn (string $raw) => LegacyValues::timestamp($raw, $this->timestampFormats));
        $amount = $this->optional($line, 'amount', $cell(self::AMOUNT), fn (string $raw) => LegacyValues::amountCents($raw));
        $receipt = $this->optional($line, 'receipt_number', $cell(self::RECEIPT), fn (string $raw) => LegacyValues::receipt($raw));
        $email = filter_var($cell(self::EMAIL), FILTER_VALIDATE_EMAIL) ? mb_strtolower($cell(self::EMAIL)) : '';

        if ($email === '' && $cell(self::EMAIL) !== '') {
            $this->warn($line, 'email', $cell(self::EMAIL), 'E-mail address is not valid; the organization is imported without it.');
        }

        $typeCode = LegacyValues::vehicleTypeCode($cell(self::VEHICLE_TYPE), $this->typeMap);

        if ($typeCode === null || ! isset($this->typeIds[$typeCode])) {
            $this->warn($line, 'vehicle_type', $cell(self::VEHICLE_TYPE), 'Vehicle type is not in database/import/vehicle_type_map.php; imported as "'.self::UNMAPPED_TYPE.'".');
            $typeCode = self::UNMAPPED_TYPE;
        }

        if ($amount === null && $cell(self::AMOUNT) === '') {
            $this->warn($line, 'amount', null, 'No amount paid; the permit is imported without a fee.');
        }

        if ($receipt === null && $cell(self::RECEIPT) === '') {
            $this->warn($line, 'receipt_number', null, 'No receipt number; no payment is recorded.');
        }

        if ($cell(self::OWNER) === '') {
            $this->warn($line, 'owner', null, 'Vehicle owner is empty.');
        }

        // Writes.
        $organizationId = $this->organizationId($organization['name'], $organization['key'], $email);
        $vehicleIds = $this->vehicleIds($line, $plates, $organizationId, $this->typeId($typeCode), $cell(self::OWNER));

        $status = $validTo->toDateString() >= ValidityRules::today()->toDateString() ? PermitStatus::Issued : PermitStatus::Expired;
        $blocking = $status->blocksVehicles() && ! $this->overlaps($line, $vehicleIds, $validFrom, $validTo);

        $permit = new Permit;
        $permit->forceFill([
            'permit_number' => $this->numbers->next($submittedAt ?? CarbonImmutable::now()),
            'organization_id' => $organizationId,
            'applicant_id' => $this->systemUser->id,
            'permit_type' => $permitType,
            'status' => $status,
            'reason' => $cell(self::REASON),
            'valid_from' => $validFrom->toDateString(),
            'valid_to' => $validTo->toDateString(),
            'fee_cents' => $amount,
            'currency' => 'ETB',
            'issued_at' => $submittedAt,
            'submitted_at' => $submittedAt,
            'legacy_source_ref' => $reference,
        ])->save();

        foreach ($vehicleIds as $index => $vehicleId) {
            DB::table('permit_vehicles')->insert([
                'permit_id' => $permit->id,
                'vehicle_id' => $vehicleId,
                'role' => $index === 0 ? VehicleRole::Primary->value : VehicleRole::Trailer->value,
                'period_from' => $validFrom->toDateString(),
                'period_to' => $validTo->toDateString(),
                'is_blocking' => $blocking,
            ]);
        }

        $history = new PermitStatusTransition([
            'from_status' => null,
            'to_status' => $status,
            'actor_id' => null,
            'note' => "Imported from the legacy sheet (batch {$this->batch->id}, line {$line}).",
        ]);
        $history->permit_id = $permit->id;
        $history->save();

        if ($amount !== null && $receipt !== null) {
            $this->payment($line, $permit, $amount, $receipt, $submittedAt ?? $validFrom);
        }

        $this->totals['imported']++;

        if ($status->blocksVehicles() && ! $blocking) {
            $this->totals['not_blocking']++;
        }
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $read
     * @return T
     *
     * @throws RowSkipped
     */
    private function required(int $line, string $field, string $raw, callable $read): mixed
    {
        try {
            return $read($raw);
        } catch (InvalidLegacyValue $invalid) {
            throw new RowSkipped($field, $raw, $invalid->getMessage());
        }
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $read
     * @return T|null
     */
    private function optional(int $line, string $field, string $raw, callable $read): mixed
    {
        if ($raw === '') {
            return null;
        }

        try {
            return $read($raw);
        } catch (InvalidLegacyValue $invalid) {
            $this->warn($line, $field, $raw, $invalid->getMessage());

            return null;
        }
    }

    /**
     * The plates of the row, primary first. An unreadable primary plate skips
     * the row; an unreadable trailer is left out with a warning.
     *
     * @return list<PlateNumber>
     *
     * @throws RowSkipped
     */
    private function plates(int $line, string $raw): array
    {
        $parts = $this->required($line, 'plate', $raw, fn (string $value) => LegacyValues::plates($value));
        $plates = [];

        foreach ($parts as $index => $part) {
            $plate = PlateNumber::tryParse($part);

            if ($plate === null) {
                if ($index === 0) {
                    throw new RowSkipped('plate', $raw, "Plate \"{$part}\" is not a valid plate number.");
                }

                $this->warn($line, 'plate', $part, 'Trailer plate is not a valid plate number; the trailer is left out.');

                continue;
            }

            if (isset($plates[$plate->normalized])) {
                $this->warn($line, 'plate', $part, 'The same plate is listed twice; listed once.');

                continue;
            }

            $plates[$plate->normalized] = $plate;
        }

        return array_values($plates);
    }

    private function organizationId(string $name, string $key, string $email): int
    {
        if (isset($this->organizations[$key])) {
            return $this->organizations[$key];
        }

        $organization = new Organization;
        $organization->forceFill([
            'name_am' => $name,
            'name_en' => null,
            'tin' => null,
            // The sheet has no phone or address; the organization completes them when it claims its account.
            'phone' => '',
            'email' => $email,
            'address' => '',
            'created_by' => $this->systemUser->id,
        ])->save();

        $this->organizations[$key] = $organization->id;
        $this->rowCache['organizations'][] = $key;
        $this->totals['organizations_created']++;

        return $organization->id;
    }

    /**
     * @param  list<PlateNumber>  $plates
     * @return list<string>
     */
    private function vehicleIds(int $line, array $plates, int $organizationId, int $typeId, string $owner): array
    {
        $ids = [];

        foreach ($plates as $plate) {
            if (! isset($this->vehicles[$plate->normalized])) {
                $existing = Vehicle::query()->where('plate_normalized', $plate->normalized)->first(['id', 'organization_id']);

                if ($existing !== null) {
                    $this->vehicles[$plate->normalized] = ['id' => $existing->id, 'organization_id' => $existing->organization_id];
                }
            }

            $known = $this->vehicles[$plate->normalized] ?? null;

            if ($known === null) {
                $vehicle = new Vehicle;
                $vehicle->forceFill([
                    'organization_id' => $organizationId,
                    'vehicle_type_id' => $typeId,
                    'plate_raw' => $plate->raw,
                    'plate_normalized' => $plate->normalized,
                    'region_code' => $plate->region->value,
                    'owner_name' => $owner,
                ])->save();

                $known = ['id' => $vehicle->id, 'organization_id' => $organizationId];
                $this->vehicles[$plate->normalized] = $known;
                $this->rowCache['vehicles'][] = $plate->normalized;
                $this->totals['vehicles_created']++;
            } elseif ($known['organization_id'] !== $organizationId) {
                $this->warn($line, 'plate', $plate->raw, 'This vehicle is already registered to another organization; it stays with that one.');
            }

            $ids[] = $known['id'];
        }

        return $ids;
    }

    /**
     * Does any of the vehicles already have a reserving permit for some of these days?
     *
     * @param  list<string>  $vehicleIds
     */
    private function overlaps(int $line, array $vehicleIds, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        $conflict = DB::table('permit_vehicles')
            ->join('vehicles', 'vehicles.id', '=', 'permit_vehicles.vehicle_id')
            ->whereIn('permit_vehicles.vehicle_id', $vehicleIds)
            ->where('permit_vehicles.is_blocking', true)
            ->where('permit_vehicles.period_from', '<=', $to->toDateString())
            ->where('permit_vehicles.period_to', '>=', $from->toDateString())
            ->value('vehicles.plate_normalized');

        if ($conflict === null) {
            return false;
        }

        $this->warn($line, 'validity', null, "Vehicle {$conflict} already has a permit for some of these days; imported without reserving the vehicles.");

        return true;
    }

    private function payment(int $line, Permit $permit, int $amount, string $receipt, CarbonImmutable $paidAt): void
    {
        if (Payment::query()->where('receipt_number', $receipt)->where('status', '!=', PaymentStatus::Rejected)->exists()) {
            $this->warn($line, 'receipt_number', $receipt, 'This receipt number is already recorded for another payment; no payment is recorded for this row.');

            return;
        }

        $payment = new Payment;
        $payment->forceFill([
            'permit_id' => $permit->id,
            'method' => PaymentMethod::Legacy,
            'receipt_number' => $receipt,
            'amount_cents' => $amount,
            'paid_at' => $paidAt->setTimezone((string) config('localization.display_timezone'))->toDateString(),
            'status' => PaymentStatus::Verified,
            'verified_at' => $paidAt,
            'notes' => 'Imported from the legacy sheet.',
        ])->save();

        $this->totals['payments_created']++;
    }

    private function systemUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::SYSTEM_USER_EMAIL],
            ['name' => 'Legacy import (Google Form)', 'password' => Str::random(64), 'locale' => 'am'],
        );
    }

    private function loadCaches(): void
    {
        $this->typeIds = VehicleType::query()->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all();

        Organization::query()->select(['id', 'name_am'])->orderBy('id')->each(function (Organization $organization): void {
            $this->organizations[LegacyValues::organization($organization->name_am)['key']] ??= $organization->id;
        });
    }

    private function typeId(string $code): int
    {
        if (! isset($this->typeIds[$code])) {
            $type = VehicleType::query()->firstOrCreate(['code' => $code], [
                'name_am' => 'ያልተለየ (ከቀድሞው መዝገብ)',
                'name_en' => 'Unmapped (legacy sheet)',
                // Not offered to applicants; only legacy vehicles have it.
                'is_active' => false,
                'sort_order' => 999,
            ]);

            $this->typeIds[$code] = $type->id;
            $this->rowCache['types'][] = $code;
        }

        return $this->typeIds[$code];
    }

    /**
     * A row that failed after creating an organization or vehicle was rolled
     * back: forget them so later rows do not point at rows that do not exist.
     */
    private function forgetRowCache(): void
    {
        foreach ($this->rowCache['organizations'] as $key) {
            unset($this->organizations[$key]);
            $this->totals['organizations_created']--;
        }

        foreach ($this->rowCache['vehicles'] as $plate) {
            unset($this->vehicles[$plate]);
            $this->totals['vehicles_created']--;
        }

        foreach ($this->rowCache['types'] as $code) {
            unset($this->typeIds[$code]);
        }
    }

    private function warn(int $line, string $field, ?string $raw, string $message): void
    {
        $this->rowIssues[] = $this->issue($line, $field, $raw, $message, IssueSeverity::Warning);
    }

    /**
     * @return array{source_row: int, field: string, raw_value: string|null, message: string, severity: string}
     */
    private function issue(int $line, string $field, ?string $raw, string $message, IssueSeverity $severity): array
    {
        return [
            'source_row' => $line,
            'field' => $field,
            'raw_value' => $raw === null ? null : mb_substr($raw, 0, 2000),
            'message' => mb_substr($message, 0, 500),
            'severity' => $severity->value,
        ];
    }

    private function saveIssues(): void
    {
        $this->totals['warnings'] = count(array_filter($this->issues, fn (array $issue): bool => $issue['severity'] === IssueSeverity::Warning->value));

        foreach (array_chunk($this->issues, 500) as $chunk) {
            ImportIssue::query()->insert(array_map(fn (array $issue): array => ['import_batch_id' => $this->batch->id, ...$issue], $chunk));
        }

        $this->issues = [];
    }

    /**
     * The issues as a CSV for review in a spreadsheet (UTF-8 with a BOM so
     * Excel shows Ethiopic text), on the private documents disk.
     */
    private function writeIssuesCsv(): void
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return;
        }

        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, ['line', 'field', 'value', 'problem', 'severity'], escape: '');

        ImportIssue::query()
            ->where('import_batch_id', $this->batch->id)
            ->orderBy('source_row')
            ->orderBy('id')
            ->cursor()
            ->each(function (ImportIssue $issue) use ($handle): void {
                fputcsv($handle, [$issue->source_row, $issue->field, $issue->raw_value, $issue->message, $issue->severity->value], escape: '');
            });

        rewind($handle);
        Storage::disk(config('permits.documents_disk'))->put($this->batch->issuesPath(), $handle);
        fclose($handle);
    }
}
