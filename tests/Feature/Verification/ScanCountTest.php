<?php

use App\Enums\Role;
use App\Enums\ScanResult;
use App\Http\Resources\OfficerPermitVerificationResource;
use App\Models\Permit;
use App\Models\User;
use App\Models\VerificationScan;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Every check of a permit is recorded (spec §7); staff and officers see how
 * often one permit has been checked.
 */

function checked(Permit $permit, string $channel, string $at, ?int $officerId = null): void
{
    VerificationScan::query()->forceCreate([
        'permit_id' => $permit->id,
        'result' => 'valid',
        'channel' => $channel,
        'ip_hash' => str_repeat('a', 64),
        'scanned_at' => $at,
        'user_id' => $officerId,
    ]);
}

it('tells staff how often a permit was checked, how, by whom and when last', function () {
    config(['localization.display_timezone' => 'Africa/Addis_Ababa']);

    $permit = Permit::factory()->active()->create();
    $other = Permit::factory()->active()->create();
    $officer = User::factory()->create();

    checked($permit, 'qr', '2026-11-10 06:00:00', $officer->id);
    checked($permit, 'qr', '2026-11-11 09:30:00');
    checked($permit, 'lookup', '2026-11-10 08:00:00');
    checked($other, 'qr', '2026-11-12 08:00:00');

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scans.total', 3)
            ->where('scans.qr', 2)
            ->where('scans.lookup', 1)
            ->where('scans.by_officers', 1)
            ->where('scans.by_public', 2)
            ->where('scans.last.time', '12:30')
            ->has('scans.last.date.ethiopian'));
});

it('says so when a permit was never checked', function () {
    $permit = Permit::factory()->active()->create();

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scans.total', 0)
            ->where('scans.last', null));
});

it('gives the officer the total number of checks, beyond the recent ones listed', function () {
    $permit = Permit::factory()->active()->create();

    foreach (range(1, 25) as $minute) {
        checked($permit, 'qr', sprintf('2026-11-10 06:%02d:00', $minute));
    }

    $view = OfficerPermitVerificationResource::make($permit, ScanResult::Valid, 'en');

    expect($view['permit']['scan_count'])->toBe(25)
        ->and(count($view['permit']['scans']))->toBeLessThan(25);
});
