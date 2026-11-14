<?php

use App\Domain\Documents\ChromePdfRenderer;
use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Domain\Documents\PermitQrCode;
use App\Domain\Permits\VerificationTokenService;
use App\Jobs\GeneratePermitDocument;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

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
            'permit_number' => 'AACRA-2019-000042',
        ]);
});

it('renders an Amharic and an English document and stores each privately under a random name', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    $this->permit->refresh();

    foreach ([$this->permit->document_path, $this->permit->document_path_en] as $path) {
        expect($path)->toMatch('#^permits/[A-Za-z0-9]{40}\.pdf$#')
            ->and(Storage::disk('documents')->get($path))->toStartWith('%PDF');
    }

    expect($this->permit->document_path)->not->toBe($this->permit->document_path_en)
        ->and(FakePdfRenderer::$rendered)->toHaveCount(2);
});

it('prints each document in one language only', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    [$amharic, $english] = FakePdfRenderer::$rendered;

    expect($amharic)->toContain('<html lang="am">')
        ->toContain('ፈቃዱን የጠየቀው ተቋም ስም')
        ->toContain(e(config('permits.document.notes.am.0')))
        ->not->toContain(e(config('permits.document.notes.en.0')))
        ->not->toContain('Plate number')
        ->and($english)->toContain('<html lang="en">')
        ->toContain('Organisation that requested the permit')
        ->toContain(e(config('permits.document.notes.en.0')))
        ->not->toContain(e(config('permits.document.notes.am.0')))
        ->not->toContain('የተሽከርካሪ ሰሌዳ ቁጥር');
});

it('prints the entries of the paper permit and a QR code for the current token', function () {
    Payment::factory()->for($this->permit)->verified()->create(['receipt_number' => 'RCPT-230729']);
    $this->permit->approver->forceFill(['name' => 'ከበበው ሚደቅሳ'])->save();

    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    $html = FakePdfRenderer::$rendered[0];

    expect($html)
        ->toContain('AACRA-2019-000042')
        ->toContain('አአ-03-A45301')
        ->toContain('አበበ የጭነት ማጓጓዣ')
        // The Amharic page gives the Ethiopian date only.
        ->toContain(EthiopianCalendar::present($this->permit->valid_from)['ethiopian'])
        ->not->toContain($this->permit->valid_from->toDateString())
        ->toContain('RCPT-230729')
        ->toContain('ከበበው ሚደቅሳ')
        ->toContain('ማሳሰቢያ')
        ->toContain('<svg')
        ->toContain('font/woff2;base64,');
});

it('is one A4 landscape page set in Abyssinica SIL', function () {
    $html = PermitDocument::html($this->permit, $this->token);

    expect($html)->toContain('size: A4 landscape')
        ->toContain("font-family: 'Abyssinica SIL'")
        ->not->toContain('Noto Sans');
});

it('never prints the fee, contact details or the token in clear text', function () {
    (new GeneratePermitDocument($this->permit->id, $this->token))->handle(app(PdfRenderer::class));

    expect(FakePdfRenderer::$rendered)->toHaveCount(2);

    foreach (FakePdfRenderer::$rendered as $html) {
        expect($html)
            ->not->toContain('12,345')
            ->not->toContain('secret@example.test')
            ->not->toContain('0911000000')
            ->not->toContain($this->token);
    }
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
        ->and($this->permit->document_path_en)->toBeNull()
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
        ->and($this->permit->document_path_en)->not->toBeNull()
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

describe('Chrome renderer', function () {
    /**
     * A stand-in for Chrome: writes the given bytes where Chrome was told to
     * print, and remembers the command it was given.
     */
    function fakeChrome(string $pdf, int $exitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($pdf, $exitCode) {
            $command = (array) $process->command;
            $target = substr((string) collect($command)->first(fn (string $arg): bool => str_starts_with($arg, '--print-to-pdf=')), 15);

            if ($exitCode === 0 && $pdf !== '') {
                file_put_contents($target, $pdf);
            }

            return Process::result(errorOutput: $exitCode === 0 ? '' : 'Chrome crashed', exitCode: $exitCode);
        });
    }

    it('prints the HTML with headless Chrome and returns the PDF', function () {
        fakeChrome('%PDF-1.4 printed');

        $pdf = (new ChromePdfRenderer('/usr/bin/google-chrome'))->render('<html>ሰላም</html>');

        expect($pdf)->toBe('%PDF-1.4 printed');

        Process::assertRan(function (PendingProcess $process): bool {
            $command = (array) $process->command;
            $page = (string) end($command);

            return $command[0] === '/usr/bin/google-chrome'
                && in_array('--headless=new', $command, true)
                && in_array('--no-pdf-header-footer', $command, true)
                && collect($command)->contains(fn (string $arg): bool => str_starts_with($arg, '--user-data-dir='))
                && str_starts_with($page, 'file:///')
                && str_ends_with($page, '/index.html');
        });
    });

    it('leaves no files behind', function () {
        fakeChrome('%PDF-1.4 printed');
        $before = glob(sys_get_temp_dir().'/heavy-pdf-*') ?: [];

        (new ChromePdfRenderer('/usr/bin/google-chrome'))->render('<html></html>');

        expect(glob(sys_get_temp_dir().'/heavy-pdf-*') ?: [])->toBe($before);
    });

    it('fails loudly when Chrome fails or prints no PDF', function (string $pdf, int $exitCode, string $message) {
        fakeChrome($pdf, $exitCode);

        expect(fn () => (new ChromePdfRenderer('/usr/bin/google-chrome'))->render('<html></html>'))
            ->toThrow(RuntimeException::class, $message);
    })->with([
        'Chrome exits with an error' => ['', 1, 'exit code 1'],
        'Chrome writes nothing' => ['', 0, 'no PDF'],
        'Chrome writes something else' => ['<html>not a pdf</html>', 0, 'no PDF'],
    ]);

    it('renders a real PDF with Ethiopic text (integration smoke test)', function () {
        $binary = (string) env('CHROME_BINARY', 'google-chrome');

        if (! is_file($binary) && (new ExecutableFinder)->find($binary) === null) {
            $this->markTestSkipped("Chrome is not installed at [{$binary}].");
        }

        $pdf = (new ChromePdfRenderer($binary))->render(PermitDocument::html($this->permit, $this->token));

        expect($pdf)->toStartWith('%PDF')
            ->and(strlen($pdf))->toBeGreaterThan(10_000)
            // A4 landscape, the page size the template asks for.
            ->and($pdf)->toMatch('#/MediaBox \[0 0 841\.9\d* 594\.9\d*\]#');
    })->group('integration');
});

it('chooses the renderer named in the configuration, and refuses an unknown one', function () {
    config(['permits.pdf_renderer' => 'chrome']);
    expect(app(PdfRenderer::class))->toBeInstanceOf(ChromePdfRenderer::class);

    config(['permits.pdf_renderer' => 'fake']);
    expect(app(PdfRenderer::class))->toBeInstanceOf(FakePdfRenderer::class);

    config(['permits.pdf_renderer' => 'gotenberg']);
    expect(fn () => app(PdfRenderer::class))->toThrow(InvalidArgumentException::class, 'PERMITS_PDF_RENDERER');
});

it('prints the logo when one is set, and a marked placeholder otherwise', function () {
    $html = PermitDocument::html($this->permit, $this->token);

    expect($html)->toContain('class="logo placeholder"')->not->toContain('<img src="data:image/');

    $file = tempnam(sys_get_temp_dir(), 'logo').'.png';
    $image = imagecreatetruecolor(8, 8);
    imagepng($image, $file);
    config(['permits.document.logo_path' => $file]);

    $html = PermitDocument::html($this->permit, $this->token);
    @unlink($file);

    expect($html)->toContain('<img src="data:image/png;base64,')->not->toContain('class="logo placeholder"');
});
