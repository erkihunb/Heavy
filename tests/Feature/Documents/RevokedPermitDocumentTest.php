<?php

use App\Actions\Permits\ExpirePermits;
use App\Actions\Permits\RevokePermit;
use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Jobs\StampPermitDocument;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
 * A revoked permit's PDFs say so, boldly, in both languages: printed again
 * when it is revoked, so a copy exported afterwards cannot pass for valid.
 */

beforeEach(function () {
    Storage::fake('documents');
    config(['permits.pdf_renderer' => 'fake']);
    FakePdfRenderer::reset();

    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->create())
        ->revoked()
        ->create(['permit_number' => 'AACRA-2019-000077']);
});

it('stamps a revoked permit "የተሰረዘ" in Amharic and "REVOKED" in English', function () {
    $amharic = PermitDocument::html($this->permit, null, 'am');
    $english = PermitDocument::html($this->permit, null, 'en');

    expect($amharic)->toContain('class="revoked-stamp">የተሰረዘ<')
        ->toContain('class="revoked-line"')
        ->and($english)->toContain('class="revoked-stamp">REVOKED<')
        ->toContain('This permit was revoked on');
});

it('shows "revoked" where the QR code was when the token is not known', function () {
    $html = PermitDocument::html($this->permit, null, 'en');

    // The security background is an SVG too, so look for the QR code's own box.
    expect($html)->toContain('class="qr-revoked"')->not->toContain('<div class="qr">');
});

it('does not stamp a permit in force', function () {
    $permit = Permit::factory()->forVehicles(Vehicle::factory()->create())->active()->create();

    expect(PermitDocument::html($permit, 'token', 'am'))->not->toContain('class="revoked-stamp"')
        ->not->toContain('class="revoked-line"');
});

it('prints both PDFs again when the permit is revoked', function () {
    Notification::fake();
    Queue::fake();
    $permit = Permit::factory()->forVehicles(Vehicle::factory()->create())->active()->create();

    app(RevokePermit::class)->handle($permit, userWithRole(Role::Approver), 'Forged receipt.');

    Queue::assertPushed(StampPermitDocument::class, fn (StampPermitDocument $job): bool => $job->permitId === $permit->id);
});

it('replaces the stored PDFs with stamped ones and keeps the token', function () {
    Storage::disk('documents')->put('permits/old-am.pdf', '%PDF');
    Storage::disk('documents')->put('permits/old-en.pdf', '%PDF');
    $this->permit->forceFill(['document_path' => 'permits/old-am.pdf', 'document_path_en' => 'permits/old-en.pdf'])->save();
    $hash = $this->permit->verification_token_hash;

    (new StampPermitDocument($this->permit->id))->handle(app(PdfRenderer::class));

    $this->permit->refresh();

    expect($this->permit->document_path)->not->toBe('permits/old-am.pdf')
        ->and($this->permit->document_path_en)->not->toBe('permits/old-en.pdf')
        ->and($this->permit->verification_token_hash)->toBe($hash)
        ->and(FakePdfRenderer::$rendered)->toHaveCount(2)
        ->and(FakePdfRenderer::$rendered[0])->toContain('የተሰረዘ')
        ->and(FakePdfRenderer::$rendered[1])->toContain('REVOKED');

    Storage::disk('documents')->assertMissing(['permits/old-am.pdf', 'permits/old-en.pdf']);
    Storage::disk('documents')->assertExists([$this->permit->document_path, $this->permit->document_path_en]);

    $entry = Activity::query()->where('event', 'permit.document_stamped')->sole();

    expect($entry->subject_id)->toEqual($this->permit->id)
        ->and($entry->properties->get('context')['stamp'])->toBe('revoked');
});

it('does nothing for a permit that is no longer revoked', function () {
    Storage::disk('documents')->put('permits/old-am.pdf', '%PDF');
    $this->permit->forceFill(['status' => PermitStatus::Issued, 'document_path' => 'permits/old-am.pdf'])->save();

    (new StampPermitDocument($this->permit->id))->handle(app(PdfRenderer::class));

    expect(FakePdfRenderer::$rendered)->toBe([])
        ->and($this->permit->refresh()->document_path)->toBe('permits/old-am.pdf');
});

it('does nothing for a permit with no PDF yet', function () {
    (new StampPermitDocument($this->permit->id))->handle(app(PdfRenderer::class));

    expect(FakePdfRenderer::$rendered)->toBe([]);
});

describe('expired permits', function () {
    beforeEach(function () {
        $this->expired = Permit::factory()->forVehicles(Vehicle::factory()->create())->expired()->create();
    });

    it('stamps an expired permit "ጊዜው ያለፈ" in Amharic and "EXPIRED" in English, in grey', function () {
        expect(PermitDocument::html($this->expired, null, 'am'))->toContain('class="revoked-stamp is-expired">ጊዜው ያለፈ<')
            ->and(PermitDocument::html($this->expired, null, 'en'))->toContain('class="revoked-stamp is-expired">EXPIRED<')
            ->toContain('This permit expired on');
    });

    it('prints both PDFs again when permits expire', function () {
        Queue::fake();
        $ending = Permit::factory()->forVehicles(Vehicle::factory()->create())->issued()
            ->validBetween(now()->subDays(20), now()->subDays(2))->create();

        app(ExpirePermits::class)->handle();

        Queue::assertPushed(StampPermitDocument::class, fn (StampPermitDocument $job): bool => $job->permitId === $ending->id);
    });

    it('replaces an expired permit\'s PDFs with stamped ones', function () {
        Storage::disk('documents')->put('permits/old-am.pdf', '%PDF');
        Storage::disk('documents')->put('permits/old-en.pdf', '%PDF');
        $this->expired->forceFill(['document_path' => 'permits/old-am.pdf', 'document_path_en' => 'permits/old-en.pdf'])->save();

        (new StampPermitDocument($this->expired->id))->handle(app(PdfRenderer::class));

        expect($this->expired->refresh()->document_path)->not->toBe('permits/old-am.pdf')
            ->and(FakePdfRenderer::$rendered[0])->toContain('ጊዜው ያለፈ')
            ->and(FakePdfRenderer::$rendered[1])->toContain('EXPIRED');
    });
});

describe('fitting one sheet', function () {
    it('keeps the normal size for ordinary values', function () {
        expect(PermitDocument::density(['Demo Organisation', 'Road construction work']))->toBe('normal');
    });

    it('sets long values smaller, step by step', function () {
        expect(PermitDocument::density([str_repeat('x', 130)]))->toBe('compact')
            ->and(PermitDocument::density([str_repeat('x', 130), str_repeat('y', 130)]))->toBe('tight');
    });

    it('cuts a very long reason with an ellipsis and sets the page tight', function () {
        $permit = Permit::factory()->forVehicles(Vehicle::factory()->create())->active()
            ->create(['reason' => str_repeat('Moving building materials to the road works site. ', 40).'THE-VERY-END']);

        $html = PermitDocument::html($permit, 'token', 'en');

        expect($html)->toContain('density-tight')
            ->toContain('…')
            ->not->toContain('THE-VERY-END');
    });
});
