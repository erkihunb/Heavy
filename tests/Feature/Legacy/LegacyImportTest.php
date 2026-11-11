<?php

use App\Domain\Legacy\LegacyImporter;
use App\Enums\ImportStatus;
use App\Enums\IssueSeverity;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The legacy import (spec §13) against a fixture with the messy values of
 * the real sheet. "Today" is fixed to Hidar 6, 2018 (2025-11-15) so issued /
 * expired is decided the same way every time.
 */

const LEGACY_FIXTURE = __DIR__.'/../../Fixtures/legacy/form-responses.csv';

beforeEach(function () {
    Storage::fake('documents');
    $this->travelTo(CarbonImmutable::parse('2025-11-15 10:00', 'Africa/Addis_Ababa'));

    VehicleType::factory()->create(['code' => 'cargo', 'name_am' => 'የጭነት', 'name_en' => 'Cargo']);
    VehicleType::factory()->create(['code' => 'special_mobile', 'name_am' => 'ልዩ ተንቀሳቃሽ', 'name_en' => 'Special mobile']);

    $this->admin = userWithRole(Role::Admin);
});

function legacyBatch(User $admin, bool $dryRun = false): ImportBatch
{
    $batch = new ImportBatch(['file_name' => 'form-responses.csv', 'dry_run' => $dryRun]);
    $batch->started_by = $admin->id;
    $batch->save();

    return $batch;
}

/**
 * @return array<string, int>
 */
function runLegacyImport(User $admin, bool $dryRun = false, string $path = LEGACY_FIXTURE): array
{
    return app(LegacyImporter::class)->run(legacyBatch($admin, $dryRun), $path);
}

function permitForPlate(string $plate): Permit
{
    return Permit::query()
        ->whereHas('vehicles', fn ($query) => $query->where('plate_normalized', $plate)->where('permit_vehicles.role', VehicleRole::Primary->value))
        ->orderBy('valid_from')
        ->firstOrFail();
}

/**
 * @return list<array{0: int, 1: string, 2: string}>
 */
function issuesOf(ImportBatch $batch): array
{
    return ImportIssue::query()
        ->where('import_batch_id', $batch->id)
        ->orderBy('source_row')
        ->orderBy('id')
        ->get()
        ->map(fn (ImportIssue $issue): array => [$issue->source_row, $issue->field, $issue->severity->value])
        ->all();
}

it('imports the fixture with the expected totals', function () {
    expect(runLegacyImport($this->admin))->toBe([
        'rows' => 15,
        'imported' => 7,
        'already_imported' => 1,
        'skipped' => 7,
        'warnings' => 9,
        'organizations_created' => 3,
        'vehicles_created' => 7,
        'payments_created' => 4,
        'not_blocking' => 1,
    ]);

    expect(Permit::query()->count())->toBe(7)
        ->and(Organization::query()->count())->toBe(3)
        ->and(Vehicle::query()->count())->toBe(7)
        ->and(Payment::query()->count())->toBe(4);
});

it('records every row it could not import cleanly, with the field and the reason', function () {
    $batch = legacyBatch($this->admin);
    app(LegacyImporter::class)->run($batch, LEGACY_FIXTURE);

    $skipped = IssueSeverity::Skipped->value;
    $warning = IssueSeverity::Warning->value;

    expect(issuesOf($batch))->toBe([
        [5, 'amount', $warning],
        [5, 'receipt_number', $warning],
        [6, 'validity', $warning],
        [6, 'receipt_number', $warning],
        [7, 'vehicle_type', $warning],
        [8, 'plate', $skipped],
        [9, 'permit_type', $skipped],
        [10, 'validity', $skipped],
        [11, 'validity', $skipped],
        [12, 'organization', $skipped],
        [14, 'plate', $warning],
        [14, 'timestamp', $warning],
        [14, 'amount', $warning],
        [14, 'email', $warning],
        [15, 'validity', $skipped],
        [16, 'row', $skipped],
    ]);

    $issue = ImportIssue::query()->where('source_row', 11)->sole();

    expect($issue->raw_value)->toBe('31/01/2018 አስከ 05/02/2018')
        ->and($issue->message)->toContain('not a date in the Ethiopian calendar');
});

it('imports a clean row completely', function () {
    runLegacyImport($this->admin);

    $permit = permitForPlate('አአ-03-A45301')->load(['organization', 'vehicles.vehicleType', 'payments', 'transitions']);

    expect($permit->status)->toBe(PermitStatus::Issued)
        ->and($permit->permit_type)->toBe(PermitType::FullDay)
        ->and($permit->valid_from->toDateString())->toBe('2025-10-08')
        ->and($permit->valid_to->toDateString())->toBe('2026-01-06')
        ->and($permit->fee_cents)->toBe(100_000)
        ->and($permit->reason)->toBe('የግንባታ ዕቃ ማጓጓዝ')
        ->and($permit->permit_number)->toMatch('/^TMA-HV-2018-\d{6}$/')
        ->and($permit->submitted_at?->toIso8601String())->toBe('2025-10-08T06:15:02+00:00')
        ->and($permit->legacy_source_ref)->toHaveLength(64)
        ->and($permit->verification_token_hash)->toBeNull()
        ->and($permit->organization->name_am)->toBe('አበበ የጭነት ማጓጓዣ')
        ->and($permit->organization->email)->toBe('abebe@example.com')
        ->and($permit->vehicles->sole()->vehicleType->code)->toBe('cargo')
        ->and($permit->vehicles->sole()->owner_name)->toBe('አበበ ከበደ');

    $payment = $permit->payments->sole();

    expect($payment->method)->toBe(PaymentMethod::Legacy)
        ->and($payment->status)->toBe(PaymentStatus::Verified)
        ->and($payment->receipt_number)->toBe('228740')
        ->and($payment->amount_cents)->toBe(100_000);

    $history = $permit->transitions->sole();

    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(PermitStatus::Issued)
        ->and($history->actor_id)->toBeNull()
        ->and($history->note)->toContain('line 2');
});

it('splits a tractor and trailer into a primary vehicle and a trailer', function () {
    runLegacyImport($this->admin);

    $permit = permitForPlate('ኢት-03-A33490');
    $roles = DB::table('permit_vehicles')
        ->join('vehicles', 'vehicles.id', '=', 'permit_vehicles.vehicle_id')
        ->where('permit_id', $permit->id)
        ->orderBy('role')
        ->pluck('role', 'vehicles.plate_normalized')
        ->all();

    expect($roles)->toBe(['ኢት-03-A33490' => 'primary', 'ኢት-03-40134' => 'trailer'])
        ->and($permit->fee_cents)->toBe(600_000)
        ->and($permit->payments()->sole()->receipt_number)->toBe('FS228741');
});

it('marks permits whose last day has passed as expired, and they reserve nothing', function () {
    runLegacyImport($this->admin);

    $permit = permitForPlate('ኢት-03-A33490');

    expect($permit->status)->toBe(PermitStatus::Expired)
        ->and(DB::table('permit_vehicles')->where('permit_id', $permit->id)->where('is_blocking', true)->count())->toBe(0);
});

it('recognises the same organization however it was typed', function () {
    runLegacyImport($this->admin);

    expect(permitForPlate('ኢት-03-A33490')->organization_id)->toBe(permitForPlate('አአ-03-A45301')->organization_id)
        ->and(Organization::query()->pluck('name_am')->all())->toEqualCanonicalizing(['ሀበሻ ኮንስትራክሽን', 'ዳና ትራንስፖርት', 'አበበ የጭነት ማጓጓዣ']);
});

it('imports an overlapping permit without letting it reserve the vehicle', function () {
    runLegacyImport($this->admin);

    $first = permitForPlate('አአ-03-A45301');
    $overlapping = Permit::query()
        ->whereKeyNot($first->id)
        ->whereHas('vehicles', fn ($query) => $query->where('plate_normalized', 'አአ-03-A45301'))
        ->sole();

    expect($overlapping->status)->toBe(PermitStatus::Issued)
        ->and((bool) DB::table('permit_vehicles')->where('permit_id', $first->id)->value('is_blocking'))->toBeTrue()
        ->and((bool) DB::table('permit_vehicles')->where('permit_id', $overlapping->id)->value('is_blocking'))->toBeFalse()
        // Its receipt was already used by the first permit.
        ->and($overlapping->payments()->count())->toBe(0);
});

it('imports an unmapped vehicle type as "unmapped", not offered to applicants', function () {
    runLegacyImport($this->admin);

    $type = permitForPlate('ደሕ-03-11111')->vehicles()->sole()->vehicleType;

    expect($type->code)->toBe(LegacyImporter::UNMAPPED_TYPE)
        ->and($type->is_active)->toBeFalse();
});

it('keeps a row with bad optional values, leaving those values out', function () {
    runLegacyImport($this->admin);

    $permit = permitForPlate('አአ-03-B22222');

    expect($permit->vehicles()->count())->toBe(1)
        ->and($permit->submitted_at)->toBeNull()
        ->and($permit->fee_cents)->toBeNull()
        ->and($permit->organization->name_am)->toBe('ዳና ትራንስፖርት');
});

it('creates no accounts for the people in the sheet', function () {
    $before = User::query()->count();

    runLegacyImport($this->admin);

    $system = User::query()->where('email', LegacyImporter::SYSTEM_USER_EMAIL)->sole();

    expect(User::query()->count())->toBe($before + 1)
        ->and(User::query()->where('email', 'abebe@example.com')->exists())->toBeFalse()
        ->and($system->getRoleNames()->all())->toBe([])
        ->and($system->email_verified_at)->toBeNull()
        ->and(Permit::query()->distinct()->pluck('applicant_id')->all())->toBe([$system->id]);
});

it('is idempotent: a second run imports nothing again', function () {
    runLegacyImport($this->admin);
    $permits = Permit::query()->count();
    $numbers = Permit::query()->pluck('permit_number')->sort()->values()->all();

    $second = runLegacyImport($this->admin);

    expect($second['imported'])->toBe(0)
        ->and($second['already_imported'])->toBe(8)
        ->and($second['organizations_created'])->toBe(0)
        ->and($second['vehicles_created'])->toBe(0)
        ->and(Permit::query()->count())->toBe($permits)
        ->and(Permit::query()->pluck('permit_number')->sort()->values()->all())->toBe($numbers)
        ->and(PermitStatusTransition::query()->count())->toBe($permits);
});

it('writes nothing in a dry run, but reports the same totals and issues', function () {
    $batch = legacyBatch($this->admin, dryRun: true);
    $totals = app(LegacyImporter::class)->run($batch, LEGACY_FIXTURE);

    expect($totals['imported'])->toBe(7)
        ->and($totals['skipped'])->toBe(7)
        ->and(Permit::query()->count())->toBe(0)
        ->and(Organization::query()->count())->toBe(0)
        ->and(Vehicle::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(DB::table('permit_number_sequences')->count())->toBe(0)
        ->and(VehicleType::query()->where('code', LegacyImporter::UNMAPPED_TYPE)->exists())->toBeFalse()
        ->and(ImportIssue::query()->where('import_batch_id', $batch->id)->count())->toBe(16)
        ->and($batch->refresh()->status)->toBe(ImportStatus::Completed)
        ->and($batch->dry_run)->toBeTrue();

    // A real run afterwards imports everything.
    expect(runLegacyImport($this->admin)['imported'])->toBe(7);
});

it('writes an issues CSV for review in a spreadsheet', function () {
    $batch = legacyBatch($this->admin);
    app(LegacyImporter::class)->run($batch, LEGACY_FIXTURE);

    $csv = Storage::disk('documents')->get($batch->issuesPath());

    expect($csv)->toStartWith("\u{FEFF}line,field,value,problem,severity")
        ->toContain('31/01/2018 አስከ 05/02/2018')
        ->and(substr_count(trim($csv), "\n"))->toBe(16);
});

it('records the batch: who ran it, when, and the totals', function () {
    $batch = legacyBatch($this->admin);
    app(LegacyImporter::class)->run($batch, LEGACY_FIXTURE);
    $batch->refresh();

    expect($batch->status)->toBe(ImportStatus::Completed)
        ->and($batch->started_by)->toBe($this->admin->id)
        ->and($batch->started_at)->not->toBeNull()
        ->and($batch->finished_at)->not->toBeNull()
        ->and($batch->totals['imported'])->toBe(7);
});

it('marks the batch failed when the file cannot be read', function () {
    $batch = legacyBatch($this->admin);

    expect(fn () => app(LegacyImporter::class)->run($batch, '/nowhere/missing.csv'))->toThrow(RuntimeException::class);

    expect($batch->refresh()->status)->toBe(ImportStatus::Failed)
        ->and($batch->error)->toContain('cannot be read');
});

it('processes files larger than one chunk', function () {
    $path = tempnam(sys_get_temp_dir(), 'legacy');
    $handle = fopen($path, 'w');
    fputcsv($handle, ['Timestamp', 'Email', 'Owner', 'Org', 'Type', 'Vehicle', 'Plate', 'Reason', 'Validity', 'Amount', 'Receipt', 'QR'], escape: '');

    for ($i = 1; $i <= 450; $i++) {
        fputcsv($handle, [
            "10/8/2025 9:15:{$i}", "bulk{$i}@example.com", 'Owner', 'Bulk Org '.($i % 10), 'ሙሉ ቀን', 'የጭነት',
            sprintf('አአ-03-A%05d', $i), 'ምክንያት', '28/01/2018 አስከ 28/04/2018', '1000', "BULK-{$i}", '',
        ], escape: '');
    }

    fclose($handle);

    $totals = runLegacyImport($this->admin, path: $path);
    unlink($path);

    expect($totals['rows'])->toBe(450)
        ->and($totals['imported'])->toBe(450)
        ->and($totals['organizations_created'])->toBe(10)
        ->and(Permit::query()->count())->toBe(450);
});

it('runs from the command line, including a dry run', function () {
    $this->artisan('legacy:import', ['file' => LEGACY_FIXTURE, '--dry-run' => true, '--user' => $this->admin->email])
        ->expectsOutputToContain('Dry run of form-responses.csv')
        ->expectsOutputToContain('Dry run: nothing was imported.')
        ->assertSuccessful();

    expect(Permit::query()->count())->toBe(0);

    $this->artisan('legacy:import', ['file' => LEGACY_FIXTURE])->assertSuccessful();

    expect(Permit::query()->count())->toBe(7)
        ->and(ImportBatch::query()->latest('id')->first()->started_by)->toBe($this->admin->id);
});

it('refuses to run for someone who may not import', function () {
    $reviewer = userWithRole(Role::Reviewer);

    $this->artisan('legacy:import', ['file' => LEGACY_FIXTURE, '--user' => $reviewer->email])
        ->expectsOutputToContain('Admin')
        ->assertFailed();

    expect(Permit::query()->count())->toBe(0);
});
