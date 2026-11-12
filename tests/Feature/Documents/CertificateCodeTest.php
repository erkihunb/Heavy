<?php

use App\Domain\Documents\CertificateCode;
use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Domain\Permits\VerificationTokenService;
use App\Jobs\GeneratePermitDocument;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;

/*
 * The certificate code printed on each permit PDF: computed with the app key
 * from what the permit says, so an altered printout no longer matches the code
 * the verification page shows. Its rule is an Admin setting.
 */

beforeEach(function () {
    config([
        'permits.certificate_code.enabled' => true,
        'permits.certificate_code.prefix' => 'CRT',
        'permits.certificate_code.length' => 8,
    ]);

    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->withPlate('ኢት-03-40134')->create())
        ->active()
        ->create(['permit_number' => 'AACRA-2019-000123']);
});

it('is the prefix and groups of four unambiguous characters', function () {
    expect(CertificateCode::for($this->permit))->toMatch('/^CRT-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/');
});

it('is the same each time for the same permit', function () {
    expect(CertificateCode::for($this->permit))->toBe(CertificateCode::for($this->permit->fresh()));
});

it('changes when what the permit says changes', function () {
    $before = CertificateCode::for($this->permit);

    $this->permit->valid_to = $this->permit->valid_to->addDay();

    expect(CertificateCode::for($this->permit))->not->toBe($before);
});

it('cannot be worked out without the app key', function () {
    $before = CertificateCode::for($this->permit);

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    expect(CertificateCode::for($this->permit))->not->toBe($before);
});

it('follows the prefix and length set by the Admin', function () {
    config(['permits.certificate_code.prefix' => '', 'permits.certificate_code.length' => 12]);

    expect(CertificateCode::for($this->permit))->toMatch('/^[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/');
});

it('is left out when the Admin switches it off', function () {
    config(['permits.certificate_code.enabled' => false]);

    expect(CertificateCode::for($this->permit))->toBeNull()
        ->and(PermitDocument::html($this->permit, 'token', 'en'))->not->toContain('class="fill code"');
});

it('is printed on the PDF and kept on the permit as printed', function () {
    Storage::fake('documents');
    config(['permits.pdf_renderer' => 'fake']);
    FakePdfRenderer::reset();
    ['token' => $token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();
    $this->permit->forceFill(['verification_token_hash' => $hash])->save();

    (new GeneratePermitDocument($this->permit->id, $token))->handle(app(PdfRenderer::class));

    $code = $this->permit->refresh()->certificate_code;

    expect($code)->toBe(CertificateCode::for($this->permit))
        ->and(FakePdfRenderer::$rendered[0])->toContain($code)
        ->and(FakePdfRenderer::$rendered[1])->toContain($code);

    // A later change of rule does not change the code of a permit already printed.
    config(['permits.certificate_code.prefix' => 'NEW']);

    expect($this->permit->refresh()->certificate_code)->toBe($code);
});

it('is shown on the verification page', function () {
    $this->permit->forceFill(['certificate_code' => 'CRT-ABCD-EFGH'])->save();
    ['token' => $token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();
    $this->permit->forceFill(['verification_token_hash' => $hash])->save();

    $this->get(route('verification.token', $token))
        ->assertOk()
        ->assertSee('CRT-ABCD-EFGH')
        ->assertSee(__('verification.fields.certificate_hint'));
});
