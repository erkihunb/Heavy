<?php

use App\Domain\Permits\ValidityRules;
use App\Domain\Reports\ExportDatasets;
use App\Enums\ExportDataset;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Enums\Role;
use App\Jobs\GenerateExport;
use App\Models\Export;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/*
 * Spreadsheet exports (spec §11): requested by staff who see reports, built
 * on the queue, downloaded only by the person who asked, deleted after a week.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function exportRequestedBy(User $user, array $attributes = []): Export
{
    $export = new Export(['dataset' => ExportDataset::Permits, 'format' => ExportFormat::Csv, 'filters' => null, ...$attributes]);
    $export->user_id = $user->id;
    $export->save();

    return $export->refresh();
}

function runExportJob(Export $export): Export
{
    (new GenerateExport($export->id))->handle(app(ExportDatasets::class));

    return $export->refresh();
}

/**
 * @return list<list<string>>
 */
function exportedCsv(Export $export): array
{
    $contents = (string) Storage::disk('documents')->get((string) $export->path);

    expect(str_starts_with($contents, "\xEF\xBB\xBF"))->toBeTrue('CSV exports start with a UTF-8 byte-order mark');

    $lines = preg_split('/\r?\n/', trim(substr($contents, 3)));

    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
}

beforeEach(function () {
    Storage::fake('documents');
    Notification::fake();

    $this->staff = staffUser(Role::Approver);
    $this->staff->forceFill(['locale' => 'en'])->save();
});

describe('requesting', function () {
    it('queues an export with its filters', function () {
        Queue::fake();

        $this->actingAs($this->staff)
            ->post(route('staff.exports.store'), [
                'dataset' => 'permits',
                'format' => 'xlsx',
                'status' => 'active',
                'from' => '2026-11-01',
                'to' => '2026-11-30',
            ])
            ->assertRedirect(route('staff.exports.index'))
            ->assertSessionHas('success');

        $export = Export::query()->sole();

        expect($export->user_id)->toBe($this->staff->id)
            ->and($export->dataset)->toBe(ExportDataset::Permits)
            ->and($export->format)->toBe(ExportFormat::Xlsx)
            ->and($export->status)->toBe(ExportStatus::Pending)
            ->and($export->filters)->toBe(['status' => 'active', 'from' => '2026-11-01', 'to' => '2026-11-30']);

        Queue::assertPushed(GenerateExport::class, fn (GenerateExport $job): bool => $job->exportId === $export->id);
    });

    it('validates the request', function (array $input, string $field) {
        Queue::fake();

        $this->actingAs($this->staff)
            ->post(route('staff.exports.store'), ['dataset' => 'permits', 'format' => 'csv', ...$input])
            ->assertSessionHasErrors($field);

        expect(Export::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'unknown dataset' => [['dataset' => 'users'], 'dataset'],
        'unknown format' => [['format' => 'pdf'], 'format'],
        'drafts are not for staff' => [['status' => 'draft'], 'status'],
        'a permit status for payments' => [['dataset' => 'payments', 'status' => 'issued'], 'status'],
        'a date in another format' => [['from' => '12/11/2026'], 'from'],
        'an end before the start' => [['from' => '2026-11-10', 'to' => '2026-11-01'], 'to'],
    ]);

    it('accepts a payment status for payments', function () {
        Queue::fake();

        $this->actingAs($this->staff)
            ->post(route('staff.exports.store'), ['dataset' => 'payments', 'format' => 'csv', 'status' => 'verified'])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(GenerateExport::class);
    });

    it('is only for staff who see reports', function () {
        Queue::fake();

        $this->actingAs(staffUser(Role::Reviewer))
            ->post(route('staff.exports.store'), ['dataset' => 'permits', 'format' => 'csv'])
            ->assertForbidden();

        $this->actingAs(staffUser(Role::Reviewer))->get(route('staff.exports.index'))->assertForbidden();

        Queue::assertNothingPushed();
    });

    it('lists only my own exports, newest first', function () {
        $older = exportRequestedBy($this->staff);
        $newer = exportRequestedBy($this->staff, ['format' => ExportFormat::Xlsx]);
        exportRequestedBy(staffUser(Role::Cashier));

        $newer->forceFill(['status' => ExportStatus::Completed, 'path' => 'exports/x.xlsx', 'rows' => 3])->save();

        $this->actingAs($this->staff)
            ->get(route('staff.exports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/exports/index')
                ->has('exports.data', 2)
                ->where('exports.data.0.id', $newer->id)
                ->where('exports.data.0.can_download', true)
                ->where('exports.data.0.rows', 3)
                ->where('exports.data.1.id', $older->id)
                ->where('exports.data.1.can_download', false)
                ->where('datasets', ['permits', 'payments'])
                ->where('formats', ['xlsx', 'csv'])
                ->where('keepDays', 7));
    });
});

describe('building', function () {
    it('writes permits to CSV in the requester\'s language, without drafts or withdrawn applications', function () {
        $organization = Organization::factory()->create(['name_en' => 'Abay Haulage']);
        $vehicle = Vehicle::factory()->create(['organization_id' => $organization->id]);
        $trailer = Vehicle::factory()->create(['organization_id' => $organization->id]);
        $issued = Permit::factory()->forVehicles($vehicle, $trailer)->issued()->create();
        Permit::factory()->submitted()->create();
        Permit::factory()->draft()->create();
        Permit::factory()->withdrawn()->create();

        $export = runExportJob(exportRequestedBy($this->staff));

        expect($export->status)->toBe(ExportStatus::Completed)
            ->and($export->rows)->toBe(2)
            ->and($export->path)->toStartWith('exports/')->toEndWith('.csv')
            ->and($export->finished_at)->not->toBeNull();

        $rows = exportedCsv($export);
        $row = collect($rows)->first(fn (array $row): bool => $row[0] === $issued->permit_number);

        expect($rows)->toHaveCount(3)
            ->and($rows[0][0])->toBe('Permit number')
            ->and($rows[0])->toHaveCount(16)
            ->and($row[3])->toBe('Abay Haulage')
            ->and($row[4])->toBe($vehicle->plate_normalized)
            ->and($row[5])->toBe($trailer->plate_normalized)
            ->and($row[8])->toBe($issued->valid_from->toDateString())
            ->and($row[12])->toBe('1000');

        Notification::assertSentTo($this->staff, ExportReady::class);
    });

    it('filters permits by status and by the period they are valid in', function () {
        $today = ValidityRules::today();

        $active = Permit::factory()->active()->create();
        Permit::factory()->issued()->validBetween($today->addDays(30), $today->addDays(40))->create();
        Permit::factory()->submitted()->create();

        $byStatus = runExportJob(exportRequestedBy($this->staff, ['filters' => ['status' => 'active', 'from' => null, 'to' => null]]));
        $byPeriod = runExportJob(exportRequestedBy($this->staff, ['filters' => [
            'status' => null,
            'from' => $today->toDateString(),
            'to' => $today->addDays(5)->toDateString(),
        ]]));

        expect($byStatus->rows)->toBe(1)
            ->and(exportedCsv($byStatus)[1][0])->toBe($active->permit_number)
            ->and($byPeriod->rows)->toBe(1)
            ->and(exportedCsv($byPeriod)[1][0])->toBe($active->permit_number);
    });

    it('writes payments, filtered by status and the day they were paid', function () {
        $paid = Payment::factory()->verified()->create(['receipt_number' => 'FS777', 'amount_cents' => 123_450, 'paid_at' => '2026-11-05']);
        Payment::factory()->verified()->create(['paid_at' => '2026-10-01']);
        Payment::factory()->rejected()->create(['paid_at' => '2026-11-05']);

        $export = runExportJob(exportRequestedBy($this->staff, [
            'dataset' => ExportDataset::Payments,
            'filters' => ['status' => 'verified', 'from' => '2026-11-01', 'to' => '2026-11-30'],
        ]));

        $rows = exportedCsv($export);

        expect($export->rows)->toBe(1)
            ->and($rows[0][0])->toBe('Receipt number')
            ->and($rows[1][0])->toBe('FS777')
            ->and($rows[1][3])->toBe('1234.5')
            ->and($rows[1][5])->toBe('2026-11-05')
            ->and($rows[1][6])->toBe($paid->permit->permit_number);
    });

    it('writes Excel files', function () {
        Permit::factory()->submitted()->count(2)->create();

        $export = runExportJob(exportRequestedBy($this->staff, ['format' => ExportFormat::Xlsx]));

        expect($export->path)->toEndWith('.xlsx');

        $local = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($local, Storage::disk('documents')->get((string) $export->path));

        $reader = new XlsxReader;
        $reader->open($local);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();
        unlink($local);

        expect($rows)->toHaveCount(3)->and($rows[0][0])->toBe('Permit number');
    });

    it('keeps text a spreadsheet would run as a formula as plain text', function () {
        $organization = Organization::factory()->create(['name_en' => '=HYPERLINK("https://evil.example","x")']);
        Permit::factory()->forVehicles(Vehicle::factory()->create(['organization_id' => $organization->id]))->submitted()->create();
        Payment::factory()->verified()->create(['receipt_number' => '+SUM(A1)']);

        $permits = exportedCsv(runExportJob(exportRequestedBy($this->staff)));
        $payments = exportedCsv(runExportJob(exportRequestedBy($this->staff, ['dataset' => ExportDataset::Payments])));

        expect($permits[1][3])->toBe('\'=HYPERLINK("https://evil.example","x")')
            ->and($payments[1][0])->toBe("'+SUM(A1)")
            ->and(ExportDatasets::cell('-1'))->toBe("'-1")
            ->and(ExportDatasets::cell('@x'))->toBe("'@x")
            ->and(ExportDatasets::cell(-1))->toBe(-1)
            ->and(ExportDatasets::cell('TMA-1'))->toBe('TMA-1')
            ->and(ExportDatasets::cell(null))->toBeNull();
    });

    it('does not build a finished export twice', function () {
        $export = runExportJob(exportRequestedBy($this->staff));
        $path = $export->path;

        runExportJob($export);

        expect($export->refresh()->path)->toBe($path);
        Notification::assertSentToTimes($this->staff, ExportReady::class, 1);
    });

    it('marks the export failed when the job gives up', function () {
        $export = exportRequestedBy($this->staff);

        (new GenerateExport($export->id))->failed(new RuntimeException('Disk full'));

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)
            ->and($export->error)->toBe('Disk full')
            ->and($export->finished_at)->not->toBeNull();
    });

    it('tells the requester by e-mail where to find the file', function () {
        $export = runExportJob(exportRequestedBy($this->staff));

        Notification::assertSentTo($this->staff, ExportReady::class, function (ExportReady $notification, array $channels) use ($export): bool {
            $mail = $notification->toMail($this->staff);

            return $channels === ['mail']
                && str_contains((string) $mail->subject, "#{$export->id}")
                && $mail->actionUrl === route('staff.exports.index');
        });
    });
});

describe('downloading', function () {
    it('gives the file to the person who asked for it, uncached', function () {
        $export = runExportJob(exportRequestedBy($this->staff));

        $response = $this->actingAs($this->staff)
            ->get(route('staff.exports.download', $export))
            ->assertOk()
            ->assertDownload($export->downloadName());

        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('hides other people\'s exports', function () {
        $export = runExportJob(exportRequestedBy($this->staff));

        $this->actingAs(staffUser(Role::Approver))
            ->get(route('staff.exports.download', $export))
            ->assertNotFound();

        $this->actingAs(staffUser(Role::Reviewer))
            ->get(route('staff.exports.download', $export))
            ->assertForbidden();
    });

    it('has nothing to download before the export is built', function () {
        $export = exportRequestedBy($this->staff);

        $this->actingAs($this->staff)
            ->get(route('staff.exports.download', $export))
            ->assertNotFound();
    });
});

it('deletes exports and their files after the keep period', function () {
    config(['permits.exports.keep_days' => 7]);

    $old = runExportJob(exportRequestedBy($this->staff));
    $old->forceFill(['created_at' => now()->subDays(8)])->save();
    $recent = runExportJob(exportRequestedBy($this->staff));
    $recent->forceFill(['created_at' => now()->subDays(6)])->save();

    $this->artisan('exports:prune')->assertSuccessful();

    expect(Export::query()->pluck('id')->all())->toBe([$recent->id]);
    Storage::disk('documents')->assertMissing((string) $old->path);
    Storage::disk('documents')->assertExists((string) $recent->path);
});
