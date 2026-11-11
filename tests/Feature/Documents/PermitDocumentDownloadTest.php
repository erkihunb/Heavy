<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The permit PDF is reached only through a temporary signed URL, and the
 * policy is checked again behind it (spec §8, §15).
 */

beforeEach(function () {
    Storage::fake('documents');
    Storage::disk('documents')->put('permits/abc.pdf', "%PDF-1.4\n%%EOF\n");

    $this->applicant = applicantWithOrganization();
    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->for($this->applicant->organization)->create())
        ->active()
        ->create(['document_path' => 'permits/abc.pdf', 'permit_number' => 'TMA-HV-2019-000007']);
});

function documentUrl(Permit $permit): string
{
    return URL::temporarySignedRoute('permits.document', now()->addMinutes(30), ['permit' => $permit->id]);
}

it('gives the applicant a signed link on the permit page and serves the PDF through it', function () {
    $this->actingAs($this->applicant)
        ->get(route('permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permit.document_pending', false)
            ->where('permit.document_url', fn (string $url): bool => str_contains($url, 'signature=')));

    $response = $this->actingAs($this->applicant)->get(documentUrl($this->permit))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('TMA-HV-2019-000007.pdf')
        ->not->toContain('abc')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('refuses a link without a valid signature', function () {
    $this->actingAs($this->applicant)
        ->get(route('permits.document', $this->permit))
        ->assertForbidden();

    $this->actingAs($this->applicant)
        ->get(documentUrl($this->permit).'x')
        ->assertForbidden();
});

it('refuses an expired link', function () {
    $url = documentUrl($this->permit);
    $this->travel(31)->minutes();

    $this->actingAs($this->applicant)->get($url)->assertForbidden();
});

it('pretends another organization\'s permit does not exist, even with a signed link', function () {
    $this->actingAs(applicantWithOrganization())
        ->get(documentUrl($this->permit))
        ->assertNotFound();
});

it('serves staff who can see the permit, and nobody else among staff', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))
        ->get(documentUrl($this->permit))
        ->assertStatus($status);
})->with([
    [Role::Reviewer, 200],
    [Role::Approver, 200],
    [Role::Admin, 200],
    [Role::Cashier, 404],
    [Role::TrafficOfficer, 404],
]);

it('stops serving the applicant once the permit is revoked', function () {
    $this->permit->forceFill(['status' => PermitStatus::Revoked])->save();

    $this->actingAs($this->applicant)->get(documentUrl($this->permit))->assertForbidden();
});

it('says the document is being prepared until the job has run', function () {
    $this->permit->forceFill(['document_path' => null])->save();

    $this->actingAs($this->applicant)
        ->get(route('permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permit.document_pending', true)
            ->where('permit.document_url', null));

    $this->actingAs($this->applicant)->get(documentUrl($this->permit))->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get(documentUrl($this->permit))->assertRedirect(route('login'));
});
