<?php

use App\Domain\Permits\VerificationTokenService;
use App\Domain\Verification\ScanLogger;
use App\Enums\PermitStatus;
use App\Enums\ScanChannel;
use App\Enums\ScanResult;
use App\Http\Resources\PublicPermitVerificationResource;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationScan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

/*
 * The public verification page (spec §7): server-rendered and usable without
 * JavaScript, a whitelist of non-sensitive fields, never cached or indexed,
 * rate limited per IP, and every check logged with a hashed IP.
 */

beforeEach(function () {
    $owner = User::factory()->create(['email' => 'owner-secret@example.test', 'phone' => '0911223344']);
    $this->organization = Organization::factory()->create([
        'name_am' => 'ሀበሻ ኮንስትራክሽን',
        'name_en' => 'Habesha Construction',
        'email' => 'org-secret@example.test',
        'phone' => '0115550000',
        'tin' => '0012345678',
        'created_by' => $owner->id,
    ]);
    $this->vehicle = Vehicle::factory()->for($this->organization)->withPlate('ኢት-03-40134')->create(['owner_name' => 'Owner Secret Name']);

    ['token' => $this->token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();

    $this->permit = Permit::factory()->forVehicles($this->vehicle)->active()->create([
        'verification_token_hash' => $hash,
        'permit_number' => 'AACRA-2019-000123',
        'fee_cents' => 1_500_000,
        'reason' => 'Internal reason text',
    ]);
});

/**
 * Put the permit in a state and give it the test token.
 *
 * @param  array<string, mixed>  $attributes
 */
function permitState(Permit $permit, array $attributes): void
{
    $permit->forceFill($attributes)->save();
}

describe('by QR code', function () {
    it('answers VALID for a permit in force today, in plain HTML', function () {
        $response = $this->get(route('verification.token', $this->token))
            ->assertOk()
            ->assertSee('data-result="valid"', false)
            ->assertSee(__('verification.results.valid.title'))
            ->assertSee('AACRA-2019-000123')
            ->assertSee('ኢት-03-40134')
            ->assertSee('ሀበሻ ኮንስትራክሽን');

        // No Inertia page: the answer is in the HTML itself.
        expect($response->getContent())->not->toContain('data-page=');
    });

    it('answers by the permit\'s dates and status', function (array $state, ScanResult $result) {
        $today = now(config('localization.display_timezone'))->startOfDay();
        $values = array_map(fn ($value) => $value instanceof Closure ? $value($today) : $value, $state);
        permitState($this->permit, $values);

        $this->get(route('verification.token', $this->token))
            ->assertOk()
            ->assertSee("data-result=\"{$result->value}\"", false)
            ->assertSee(__("verification.results.{$result->value}.title"));
    })->with([
        'starts tomorrow' => [['valid_from' => fn ($t) => $t->addDay()->toDateString(), 'valid_to' => fn ($t) => $t->addDays(5)->toDateString()], ScanResult::NotYetValid],
        'last day today' => [['valid_from' => fn ($t) => $t->subDays(5)->toDateString(), 'valid_to' => fn ($t) => $t->toDateString()], ScanResult::Valid],
        'ended yesterday, job not run' => [['valid_from' => fn ($t) => $t->subDays(5)->toDateString(), 'valid_to' => fn ($t) => $t->subDay()->toDateString()], ScanResult::Expired],
        'marked expired' => [['status' => PermitStatus::Expired], ScanResult::Expired],
        'revoked' => [['status' => PermitStatus::Revoked], ScanResult::Revoked],
    ]);

    it('answers NOT FOUND for an unknown, malformed or rotated token', function (string $token) {
        $this->get(route('verification.token', $token))
            ->assertOk()
            ->assertSee('data-result="not_found"', false)
            ->assertDontSee('AACRA-2019-000123');
    })->with([
        'unknown' => [str_repeat('A', 43)],
        'too short' => ['abc'],
        'odd characters' => ['abc_DEF-ghi'],
    ]);

    it('stops answering for a token once the permit is reissued', function () {
        permitState($this->permit, ['verification_token_hash' => hash('sha256', 'another')]);

        $this->get(route('verification.token', $this->token))->assertSee('data-result="not_found"', false);
    });

    it('never finds a permit that has not been issued', function (PermitStatus $status) {
        permitState($this->permit, ['status' => $status]);

        $this->get(route('verification.token', $this->token))->assertSee('data-result="not_found"', false);
    })->with([PermitStatus::AwaitingPayment, PermitStatus::PaymentSubmitted, PermitStatus::UnderReview]);

    it('shows no contact details, fees or ids', function () {
        $html = $this->get(route('verification.token', $this->token))->getContent();

        expect($html)
            ->not->toContain('owner-secret@example.test')
            ->not->toContain('org-secret@example.test')
            ->not->toContain('0911223344')
            ->not->toContain('0115550000')
            ->not->toContain('0012345678')
            ->not->toContain('15,000')
            ->not->toContain($this->permit->id)
            ->not->toContain($this->permit->verification_token_hash);
    });

    it('shows what is printed on the permit: owner, reason, receipt, issuer, branch and issue date', function () {
        Payment::factory()->for($this->permit)->verified()->create(['receipt_number' => 'RCPT-230729']);
        $this->permit->approver->forceFill(['name' => 'Kebede Approver'])->save();

        $this->get(route('verification.token', $this->token))
            ->assertOk()
            ->assertSee('Owner Secret Name')
            ->assertSee('Internal reason text')
            ->assertSee('RCPT-230729')
            ->assertSee('Kebede Approver')
            ->assertSee(__('verification.fields.issued'));
    });

    it('is never cached or indexed and does not leak the address', function () {
        $response = $this->get(route('verification.token', $this->token));

        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
            ->and($response->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
    });

    it('logs every check with a hashed IP address', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '196.188.10.20'])
            ->withHeader('User-Agent', 'TestPhone/1.0')
            ->get(route('verification.token', $this->token));

        $this->get(route('verification.token', str_repeat('A', 43)));

        $scans = VerificationScan::query()->orderBy('id')->get();

        expect($scans)->toHaveCount(2)
            ->and($scans[0]->permit_id)->toBe($this->permit->id)
            ->and($scans[0]->result)->toBe(ScanResult::Valid)
            ->and($scans[0]->channel)->toBe(ScanChannel::Qr)
            ->and($scans[0]->user_id)->toBeNull()
            ->and($scans[0]->ip_hash)->toBe(ScanLogger::hashIp('196.188.10.20'))
            ->and($scans[0]->ip_hash)->not->toContain('196.188')
            ->and($scans[0]->user_agent)->toBe('TestPhone/1.0')
            ->and($scans[1]->permit_id)->toBeNull()
            ->and($scans[1]->result)->toBe(ScanResult::NotFound);
    });

    it('limits each IP address to 30 checks a minute', function () {
        RateLimiter::clear('verification');

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('verification.token', $this->token))->assertOk();
        }

        $this->get(route('verification.token', $this->token))->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->get(route('verification.token', $this->token))->assertOk();
    });

    it('can hide the organization name', function () {
        config(['permits.public_shows_organization' => false]);

        $this->get(route('verification.token', $this->token))->assertDontSee('ሀበሻ ኮንስትራክሽን');
    });
});

describe('by number and plate', function () {
    it('shows the form without JavaScript', function () {
        $this->get(route('verification.form'))
            ->assertOk()
            ->assertSee('name="permit_number"', false)
            ->assertSee('name="plate"', false)
            ->assertSee('name="_token"', false);
    });

    it('offers the camera scanner, revealed only by its script', function () {
        $response = $this->get(route('verification.form'))
            ->assertOk()
            ->assertSee('data-qr-scanner', false)
            // Hidden in the markup, so the page works without JavaScript…
            ->assertSee('data-scan-start hidden', false)
            ->assertSee('data-verify-base="'.rtrim((string) config('permits.verify_url'), '/').'"', false);

        // …and shown by an inline script right after it, before the first paint,
        // so the form below does not jump when the scanner's script arrives.
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);
        $html = (string) $response->getContent();

        expect($nonce)->not->toBeEmpty()
            ->and($html)->toContain('<script nonce="'.$nonce[1].'">')
            ->and($html)->toContain("document.querySelector('[data-scan-start]').hidden = false;")
            ->and(strpos($html, "querySelector('[data-scan-start]').hidden = false"))->toBeGreaterThan(strpos($html, 'data-scan-start hidden'))
            ->and(strpos($html, "querySelector('[data-scan-start]').hidden = false"))->toBeLessThan(strpos($html, 'name="permit_number"'));
    });

    it('finds the permit only with a matching plate, written in any accepted form', function (string $number, string $plate, string $result) {
        $this->post(route('verification.lookup'), ['permit_number' => $number, 'plate' => $plate])
            ->assertOk()
            ->assertSee("data-result=\"{$result}\"", false);
    })->with([
        'exact' => ['AACRA-2019-000123', 'ኢት-03-40134', 'valid'],
        'lower case, spaces' => [' aacra-2019-000123 ', 'ኢት 03 40134', 'valid'],
        'wrong plate' => ['AACRA-2019-000123', 'ኢት-03-40135', 'not_found'],
        'malformed plate' => ['AACRA-2019-000123', 'nonsense', 'not_found'],
        'wrong number' => ['AACRA-2019-000124', 'ኢት-03-40134', 'not_found'],
    ]);

    it('requires both fields', function (array $data, string $field) {
        $this->from(route('verification.form'))
            ->post(route('verification.lookup'), $data)
            ->assertRedirect(route('verification.form'))
            ->assertSessionHasErrors($field);
    })->with([
        [['plate' => 'ኢት-03-40134'], 'permit_number'],
        [['permit_number' => 'AACRA-2019-000123'], 'plate'],
    ]);

    it('logs lookups too', function () {
        $this->post(route('verification.lookup'), ['permit_number' => 'AACRA-2019-000123', 'plate' => 'ኢት-03-40134']);

        expect(VerificationScan::query()->sole()->channel)->toBe(ScanChannel::Lookup);
    });
});

describe('PublicPermitVerificationResource', function () {
    it('exposes exactly the whitelisted fields', function () {
        $data = PublicPermitVerificationResource::make($this->permit, ScanResult::Valid, 'en');

        expect(array_keys($data))->toBe(['result', 'checked_at', 'checked_time', 'checked_time_ethiopian', 'permit'])
            ->and(array_keys($data['permit']))->toBe([
                'permit_number', 'permit_type', 'vehicles', 'reason', 'issued_at', 'issued_time', 'branch', 'issuer',
                'receipt_number', 'certificate_code', 'organization', 'valid_from', 'valid_to', 'time_window_rules', 'hours',
            ])
            ->and(array_keys($data['permit']['vehicles'][0]))->toBe(['plate', 'role', 'vehicle_type', 'owner']);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        // The receipt number, reason, owner and issuer are printed on the permit and shown;
        // contact details, the fee, notes and the token never are.
        foreach (['email', 'phone', 'fee', 'note', 'applicant', 'recommend', 'approved', 'token', 'tin'] as $sensitive) {
            expect($json)->not->toContain($sensitive);
        }
    });

    it('sends nothing about a permit that was not found', function () {
        expect(PublicPermitVerificationResource::make($this->permit, ScanResult::NotFound, 'en')['permit'])->toBeNull()
            ->and(PublicPermitVerificationResource::make(null, ScanResult::NotFound, 'en')['permit'])->toBeNull();
    });

    it('answers in the visitor\'s language', function () {
        $data = PublicPermitVerificationResource::make($this->permit, ScanResult::Valid, 'en');

        expect($data['permit']['organization'])->toBe('Habesha Construction')
            ->and($data['permit']['time_window_rules'])->toBe(config('permits.time_windows.full_day.en'))
            ->and($data['checked_at']['gregorian'])->toBe(CarbonImmutable::now(config('localization.display_timezone'))->toDateString());
    });
});
