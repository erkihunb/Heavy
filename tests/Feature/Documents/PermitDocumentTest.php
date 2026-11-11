<?php

use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\GotenbergPdfRenderer;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Domain\Documents\PermitQrCode;
use App\Domain\Permits\VerificationTokenService;
use App\Jobs\GeneratePermitDocument;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * The permit document (spec §8): rendered by a queued job through a
 * Chromium-based renderer, stored privately, and only ever for the token the
 * permit currently has.
 */

beforeEach(function () {
    Storage::fake('documents');
    config(['permits.pdf_renderer' => 'fake', 'permits.verify_url' => 'https://verify.example.test']);
    FakePdfRenderer::reset();

    ['token' => $this->token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();

    $organization = Organization::factory()->create(['name_am' => 'አበበ የጭነት ማጓጓዣ', 'email' => 'secret@example.test', 'phone' => '0911000000']);
    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->for($organization)->withPlate('አአ-03-A45301')->create())
        ->active()
        ->create([
            'organization_id' => $organization->id,
            'verification_token_hash' => $hash,
            'fee_cents' => 1_234_500,
            'permit_number' => 'TMA-HV-2019-000042',
        ]);
});

it('renders the document and stores it privately under a random name', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    $path = $this->permit->refresh()->document_path;

    expect($path)->toMatch('#^permits/[A-Za-z0-9]{40}\.pdf$#')
        ->and(Storage::disk('documents')->get($path))->toStartWith('%PDF');
});

it('prints the permit details, the rules and a QR code for the current token', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    $html = FakePdfRenderer::$rendered[0];

    expect($html)
        ->toContain('TMA-HV-2019-000042')
        ->toContain('አአ-03-A45301')
        ->toContain('አበበ የጭነት ማጓጓዣ')
        ->toContain($this->permit->valid_from->toDateString())
        ->toContain(e(config('permits.time_windows.full_day.am')))
        ->toContain('<svg')
        ->toContain('font/woff2;base64,');
});

it('never prints the fee, contact details or the token in clear text', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    expect(FakePdfRenderer::$rendered[0])
        ->not->toContain('12,345')
        ->not->toContain('secret@example.test')
        ->not->toContain('0911000000')
        ->not->toContain($this->token);
});

it('encodes the verification address in the QR code', function () {
    $svg = PermitQrCode::svg($this->token);

    expect($svg)->toStartWith('<svg')
        ->and(VerificationTokenService::url($this->token))->toBe("https://verify.example.test/v/{$this->token}");
});

it('does nothing for a token that has been rotated', function () {
    $this->permit->forceFill(['verification_token_hash' => str_repeat('b', 64)])->save();

    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    expect($this->permit->refresh()->document_path)->toBeNull()
        ->and(FakePdfRenderer::$rendered)->toBe([])
        ->and(Storage::disk('documents')->allFiles())->toBe([]);
});

it('does nothing for a permit that no longer exists', function () {
    (new GeneratePermitDocument('01jxxxxxxxxxxxxxxxxxxxxxxx', $this->token))->handle(app(PdfRenderer::class));

    expect(FakePdfRenderer::$rendered)->toBe([]);
});

it('runs when dispatched and retries with backoff', function () {
    GeneratePermitDocument::dispatchSync($this->permit->id, $this->token);

    $job = new GeneratePermitDocument($this->permit->id, $this->token);

    expect($this->permit->refresh()->document_path)->not->toBeNull()
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 600]);
});

it('logs a failure without the token', function () {
    Log::spy();

    (new GeneratePermitDocument($this->permit->id, $this->token))->failed(new RuntimeException('renderer down'));

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $context['permit_id'] === $this->permit->id
        && ! str_contains(json_encode($context), $this->token));
});

it('builds self-contained HTML', function () {
    $html = PermitDocument::html($this->permit, $this->token);

    // No external fonts, scripts or images: the renderer needs no network.
    expect($html)->not->toMatch('#(src|href)=["\']https?://#')
        ->not->toContain('<script');
});

describe('Gotenberg renderer', function () {
    it('posts the HTML to Chromium and returns the PDF', function () {
        Http::fake(['gotenberg.test/*' => Http::response('%PDF-1.7 fake', 200)]);

        $pdf = (new GotenbergPdfRenderer('http://gotenberg.test'))->render('<html>ሰላም</html>');

        expect($pdf)->toBe('%PDF-1.7 fake');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://gotenberg.test/forms/chromium/convert/html'
            && str_contains($request->body(), 'ሰላም')
            && str_contains($request->body(), 'filename="index.html"'));
    });

    it('fails loudly when the renderer answers with an error', function () {
        Http::fake(['gotenberg.test/*' => Http::response('boom', 503)]);

        expect(fn () => (new GotenbergPdfRenderer('http://gotenberg.test'))->render('<html></html>'))
            ->toThrow(RuntimeException::class, 'HTTP 503');
    });

    it('renders a real PDF with Ethiopic text (integration smoke test)', function () {
        $url = (string) env('GOTENBERG_URL', 'http://127.0.0.1:3000');

        try {
            Http::timeout(2)->get(rtrim($url, '/').'/health')->throw();
        } catch (Throwable) {
            $this->markTestSkipped("Gotenberg is not reachable at {$url}.");
        }

        $pdf = (new GotenbergPdfRenderer($url))->render(PermitDocument::html($this->permit, $this->token));

        expect($pdf)->toStartWith('%PDF')
            ->and(strlen($pdf))->toBeGreaterThan(10_000);
    })->group('integration');
});
