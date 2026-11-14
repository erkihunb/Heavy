<?php

use App\Domain\Audit\AuditEvent;
use App\Domain\Documents\StoredDocument;
use App\Enums\Role;
use App\Jobs\GeneratePermitDocument;
use App\Models\Permit;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
 * Permit PDFs that are missing or are placeholders (written while the test
 * renderer was configured) are never handed out, and permits:documents
 * builds them again with a new verification token.
 */

beforeEach(function () {
    Storage::fake('documents');
    Queue::fake();
    // As in production: the real renderer, whose PDFs are tens of kilobytes.
    config(['permits.pdf_renderer' => 'chrome']);
});

/**
 * @param  list<'am'|'en'>  $languages
 */
function storeDocument(Permit $permit, string $bytes, array $languages = ['am', 'en']): void
{
    foreach ($languages as $language) {
        $path = "permits/{$permit->id}-{$language}.pdf";
        Storage::disk('documents')->put($path, $bytes);
        $permit->forceFill([StoredDocument::LANGUAGES[$language] => $path]);
    }

    $permit->save();
}

const DOCUMENT_PLACEHOLDER = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

it('tells a real document from a placeholder or a missing file', function () {
    $permit = Permit::factory()->issued()->create();
    expect(StoredDocument::isUsable($permit))->toBeFalse();

    storeDocument($permit, DOCUMENT_PLACEHOLDER);
    expect(StoredDocument::isUsable($permit))->toBeFalse();

    // A permit printed in one language only is not in order yet.
    storeDocument($permit, '%PDF-1.7'.str_repeat('x', 4096), ['am']);
    expect(StoredDocument::isUsable($permit, 'am'))->toBeTrue()
        ->and(StoredDocument::isUsable($permit, 'en'))->toBeFalse()
        ->and(StoredDocument::isUsable($permit))->toBeFalse();

    storeDocument($permit, '%PDF-1.7'.str_repeat('x', 4096));
    expect(StoredDocument::isUsable($permit))->toBeTrue();
});

it('never hands out a placeholder', function () {
    $permit = Permit::factory()->issued()->create();
    storeDocument($permit, DOCUMENT_PLACEHOLDER);
    $staff = staffUser(Role::Approver);

    $this->actingAs($staff)
        ->get(URL::temporarySignedRoute('permits.document', now()->addMinutes(5), ['permit' => $permit->id]))
        ->assertNotFound();
});

it('rebuilds broken documents with a new token, and audits it', function () {
    $broken = Permit::factory()->issued()->create();
    storeDocument($broken, DOCUMENT_PLACEHOLDER);
    $missing = Permit::factory()->revoked()->create(['document_path' => null]);
    $fine = Permit::factory()->issued()->create();
    storeDocument($fine, '%PDF-1.7'.str_repeat('x', 4096));
    $draft = Permit::factory()->draft()->create();

    $oldHash = $broken->verification_token_hash;

    $this->artisan('permits:documents')->assertSuccessful();

    expect($broken->refresh()->verification_token_hash)->not->toBe($oldHash)
        ->and($broken->document_path)->toBeNull()
        ->and($broken->document_path_en)->toBeNull()
        ->and($fine->refresh()->document_path)->not->toBeNull();

    Queue::assertPushed(GeneratePermitDocument::class, 2);
    Queue::assertPushed(GeneratePermitDocument::class, fn (GeneratePermitDocument $job): bool => in_array($job->permitId, [$broken->id, $missing->id], true));
    Storage::disk('documents')->assertMissing(["permits/{$broken->id}-am.pdf", "permits/{$broken->id}-en.pdf"]);

    expect(Activity::query()->where('event', AuditEvent::DocumentRegenerated->value)->count())->toBe(2)
        ->and($draft->refresh()->document_path)->toBeNull();
});

it('rebuilds only the permits asked for', function () {
    $one = Permit::factory()->issued()->create();
    $other = Permit::factory()->issued()->create();

    $this->artisan('permits:documents', ['permit' => [$one->permit_number]])->assertSuccessful();

    Queue::assertPushed(GeneratePermitDocument::class, 1);
    Queue::assertPushed(GeneratePermitDocument::class, fn (GeneratePermitDocument $job): bool => $job->permitId === $one->id);
    expect($other->refresh()->verification_token_hash)->toBe($other->getOriginal('verification_token_hash'));
});

it('changes nothing on a dry run', function () {
    $permit = Permit::factory()->issued()->create();
    $hash = $permit->verification_token_hash;

    $this->artisan('permits:documents', ['--dry-run' => true])
        ->expectsOutputToContain('1 document(s) would be rebuilt')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect($permit->refresh()->verification_token_hash)->toBe($hash);
});

it('says so when every document is in order', function () {
    $permit = Permit::factory()->issued()->create();
    storeDocument($permit, '%PDF-1.7'.str_repeat('x', 4096));

    $this->artisan('permits:documents')
        ->expectsOutput('Every permit document is in order.')
        ->assertSuccessful();
});

it('rebuilds documents in order too when asked for all', function () {
    $fine = Permit::factory()->issued()->create();
    storeDocument($fine, '%PDF-1.7'.str_repeat('x', 4096));
    $hash = $fine->verification_token_hash;

    $this->artisan('permits:documents', ['--all' => true])
        ->expectsOutputToContain('copies printed before stop verifying')
        ->assertSuccessful();

    Queue::assertPushed(GeneratePermitDocument::class, fn (GeneratePermitDocument $job): bool => $job->permitId === $fine->id);

    $audit = Activity::query()->where('event', AuditEvent::DocumentRegenerated->value)->sole();

    expect($fine->refresh()->verification_token_hash)->not->toBe($hash)
        ->and($audit->properties['context']['reason'])->toBe('requested');
});
