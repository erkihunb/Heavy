<?php

use App\Domain\Permits\VerificationTokenService;
use App\Enums\PermitStatus;
use App\Enums\ScanResult;
use App\Models\Permit;
use Illuminate\Database\UniqueConstraintViolationException;

/*
 * The token behind a QR code (spec §7): 32 random bytes, base64url, stored
 * only as its SHA-256.
 */

it('generates 43 base64url characters from 32 random bytes', function () {
    $service = app(VerificationTokenService::class);
    $tokens = [];

    for ($i = 0; $i < 50; $i++) {
        ['token' => $token, 'hash' => $hash] = $service->generate();

        expect($token)->toMatch(VerificationTokenService::PATTERN)
            ->and(strlen((string) base64_decode(strtr($token, '-_', '+/'), true)))->toBe(32)
            ->and($hash)->toBe(hash('sha256', $token))
            ->and($hash)->toHaveLength(64);

        $tokens[] = $token;
    }

    expect(array_unique($tokens))->toHaveCount(50);
});

it('finds a permit by the hash of its token only', function () {
    ['token' => $token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();
    $permit = Permit::factory()->issued()->create(['verification_token_hash' => $hash]);

    $service = app(VerificationTokenService::class);

    expect($service->find($token)?->id)->toBe($permit->id)
        ->and($service->find($hash))->toBeNull()
        ->and($service->find(strtoupper($token)) === null || strtoupper($token) === $token)->toBeTrue()
        ->and($service->find(''))->toBeNull()
        ->and($service->find($token.'x'))->toBeNull();
});

it('keeps the token hash unique across permits', function () {
    Permit::factory()->issued()->create(['verification_token_hash' => str_repeat('c', 64)]);

    expect(fn () => Permit::factory()->issued()->create(['verification_token_hash' => str_repeat('c', 64)]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('maps each effective status to one verification result', function (PermitStatus $status, ScanResult $result) {
    expect(ScanResult::forStatus($status))->toBe($result);
})->with([
    [PermitStatus::Active, ScanResult::Valid],
    [PermitStatus::Issued, ScanResult::NotYetValid],
    [PermitStatus::Expired, ScanResult::Expired],
    [PermitStatus::Revoked, ScanResult::Revoked],
    [PermitStatus::AwaitingPayment, ScanResult::NotFound],
    [PermitStatus::Draft, ScanResult::NotFound],
]);

it('lets only a valid permit allow movement', function () {
    expect(array_values(array_filter(ScanResult::cases(), fn (ScanResult $result): bool => $result->allowsMovement())))
        ->toBe([ScanResult::Valid]);
});
