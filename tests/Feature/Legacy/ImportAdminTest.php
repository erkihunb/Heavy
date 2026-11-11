<?php

use App\Domain\Legacy\LegacyImporter;
use App\Enums\ImportStatus;
use App\Enums\Role;
use App\Jobs\RunLegacyImport;
use App\Models\ImportBatch;
use App\Models\Permit;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Admin's legacy import runner (spec §11): upload, queue, follow, and
 * download the issues; nobody else may use it.
 */

beforeEach(function () {
    Storage::fake('documents');
    $this->travelTo(CarbonImmutable::parse('2025-11-15 10:00', 'Africa/Addis_Ababa'));
    VehicleType::factory()->create(['code' => 'cargo']);
    VehicleType::factory()->create(['code' => 'special_mobile']);

    $this->admin = staffUser(Role::Admin);
});

function fixtureUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    copy(__DIR__.'/../../Fixtures/legacy/form-responses.csv', $path);

    return new UploadedFile($path, 'Form Responses 1.csv', 'text/csv', null, true);
}

it('queues an uploaded CSV as a new batch', function () {
    Bus::fake([RunLegacyImport::class]);

    $this->actingAs($this->admin)
        ->post(route('admin.imports.store'), ['file' => fixtureUpload(), 'dry_run' => '1'])
        ->assertRedirect(route('admin.imports.index'))
        ->assertSessionHas('success');

    $batch = ImportBatch::query()->sole();

    expect($batch->status)->toBe(ImportStatus::Pending)
        ->and($batch->dry_run)->toBeTrue()
        ->and($batch->file_name)->toBe('Form Responses 1.csv')
        ->and($batch->started_by)->toBe($this->admin->id)
        ->and($batch->stored_path)->toMatch('#^imports/uploads/[A-Za-z0-9]{40}\.csv$#');

    Storage::disk('documents')->assertExists($batch->stored_path);
    Bus::assertDispatched(RunLegacyImport::class, fn (RunLegacyImport $job): bool => $job->batchId === $batch->id);
});

it('runs the queued batch and lists it with its totals and issues', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.imports.store'), ['file' => fixtureUpload(), 'dry_run' => '0'])
        ->assertSessionHasNoErrors();

    $batch = ImportBatch::query()->sole();

    expect($batch->status)->toBe(ImportStatus::Completed)
        ->and(Permit::query()->count())->toBe(7);

    $this->actingAs($this->admin)
        ->get(route('admin.imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/imports/index')
            ->where('batches.data.0.id', $batch->id)
            ->where('batches.data.0.status', 'completed')
            ->where('batches.data.0.totals.imported', 7)
            ->where('batches.data.0.has_issues_file', true)
            ->where('batches.data.0.started_by', $this->admin->name)
            ->missing('batches.data.0.stored_path'));

    $response = $this->actingAs($this->admin)->get(route('admin.imports.issues', $batch))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain("import-{$batch->id}-issues.csv")
        ->and($response->streamedContent())->toContain('31/01/2018 አስከ 05/02/2018');
});

it('does not run a batch twice', function () {
    Bus::fake([RunLegacyImport::class]);
    $this->actingAs($this->admin)->post(route('admin.imports.store'), ['file' => fixtureUpload()]);
    $batch = ImportBatch::query()->sole();

    (new RunLegacyImport($batch->id))->handle(app(LegacyImporter::class));
    (new RunLegacyImport($batch->id))->handle(app(LegacyImporter::class));

    expect(Permit::query()->count())->toBe(7)
        ->and($batch->refresh()->totals['imported'])->toBe(7);
});

it('marks the batch failed when the job fails', function () {
    Bus::fake([RunLegacyImport::class]);
    $this->actingAs($this->admin)->post(route('admin.imports.store'), ['file' => fixtureUpload()]);
    $batch = ImportBatch::query()->sole();

    (new RunLegacyImport($batch->id))->failed(new RuntimeException('Worker lost.'));

    expect($batch->refresh()->status)->toBe(ImportStatus::Failed)
        ->and($batch->error)->toBe('Worker lost.');
});

it('accepts only a CSV file', function (?UploadedFile $file) {
    Bus::fake([RunLegacyImport::class]);

    $this->actingAs($this->admin)
        ->post(route('admin.imports.store'), ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(ImportBatch::query()->count())->toBe(0);
})->with([
    'missing' => [null],
    'image' => [fn () => UploadedFile::fake()->image('sheet.png')],
]);

it('keeps everyone but Admins out', function (Role $role) {
    $user = $role === Role::Applicant ? applicantWithOrganization() : staffUser($role);
    $batch = new ImportBatch(['file_name' => 'x.csv']);
    $batch->started_by = $this->admin->id;
    $batch->save();

    $this->actingAs($user)->get(route('admin.imports.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.imports.store'), ['file' => fixtureUpload()])->assertForbidden();
    $this->actingAs($user)->get(route('admin.imports.issues', $batch))->assertForbidden();

    expect(ImportBatch::query()->count())->toBe(1);
})->with([Role::Applicant, Role::Reviewer, Role::Approver, Role::Cashier, Role::TrafficOfficer]);

it('answers not found when a batch has no issues file yet', function () {
    $batch = new ImportBatch(['file_name' => 'x.csv']);
    $batch->started_by = $this->admin->id;
    $batch->save();

    $this->actingAs($this->admin)->get(route('admin.imports.issues', $batch))->assertNotFound();
});
