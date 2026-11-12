<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * The permit PDF is reached only through a temporary signed URL, and the
 * policy is checked again behind it (spec §8, §15).
 */

beforeEach(function () {
    Storage::fake('documents');
    Storage::disk('documents')->put('permits/abc.pdf', "%PDF-1.4\n%%EOF\n");
    Storage::disk('documents')->put('permits/def.pdf', "%PDF-1.4\n%%EOF\n");

    $this->applicant = applicantWithOrganization();
    $this->permit = Permit::factory()
        ->forVehicles(Vehicle::factory()->for($this->applicant->organization)->create())
        ->active()
        ->create([
            'document_path' => 'permits/abc.pdf',
            'document_path_en' => 'permits/def.pdf',
            'permit_number' => 'AACRA-2019-000007',
        ]);
});

function documentUrl(Permit $permit, ?string $language = null): string
{
    return URL::temporarySignedRoute('permits.document', now()->addMinutes(30), array_filter([
        'permit' => $permit->id,
        'language' => $language,
    ]));
}

it('gives the applicant a signed link per language on the permit page and serves the PDF through it', function () {
    $this->actingAs($this->applicant)
        ->get(route('permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permit.document_pending', false)
            ->where('permit.document_urls.am', fn (string $url): bool => str_contains($url, '/document/am?') && str_contains($url, 'signature='))
            ->where('permit.document_urls.en', fn (string $url): bool => str_contains($url, '/document/en?') && str_contains($url, 'signature=')));

    $response = $this->actingAs($this->applicant)->get(documentUrl($this->permit, 'am'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('AACRA-2019-000007-am.pdf')
        ->not->toContain('abc')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('serves the English PDF on its own link, and the Amharic one when no language is named', function () {
    $english = $this->actingAs($this->applicant)->get(documentUrl($this->permit, 'en'))->assertOk();
    $default = $this->actingAs($this->applicant)->get(documentUrl($this->permit))->assertOk();

    expect($english->headers->get('Content-Disposition'))->toContain('AACRA-2019-000007-en.pdf')
        ->not->toContain('def')
        ->and($default->headers->get('Content-Disposition'))->toContain('AACRA-2019-000007-am.pdf');
});

it('writes each download and print to the audit log', function () {
    $this->actingAs($this->applicant)->get(documentUrl($this->permit, 'en'))->assertOk();
    $this->actingAs($this->applicant)->withHeader('X-Permit-Print', '1')->get(documentUrl($this->permit, 'am'))->assertOk();

    $entries = Activity::query()->where('event', 'permit.document_downloaded')->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->subject_id)->toEqual($this->permit->id)
        ->and($entries[0]->causer_id)->toEqual($this->applicant->id)
        ->and($entries[0]->properties->get('context'))->toBe(['language' => 'en', 'purpose' => 'download'])
        ->and($entries[1]->properties->get('context'))->toBe(['language' => 'am', 'purpose' => 'print']);
});

it('knows no language but Amharic and English', function () {
    $this->actingAs($this->applicant)
        ->get('/permits/'.$this->permit->id.'/document/fr')
        ->assertNotFound();
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
    // The cashier and the head office see the application; the Issuer prints it.
    [Role::Cashier, 200],
    [Role::Issuer, 200],
    [Role::Supervisor, 200],
    [Role::TrafficOfficer, 404],
]);

it('stops serving the applicant once the permit is revoked', function () {
    $this->permit->forceFill(['status' => PermitStatus::Revoked])->save();

    $this->actingAs($this->applicant)->get(documentUrl($this->permit))->assertForbidden();
});

it('says the document is being prepared until the job has run', function () {
    // Only the Amharic PDF is there so far: neither link is offered until both are.
    $this->permit->forceFill(['document_path_en' => null])->save();

    $this->actingAs($this->applicant)
        ->get(route('permits.show', $this->permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permit.document_pending', true)
            ->where('permit.document_urls', null));

    $this->actingAs($this->applicant)->get(documentUrl($this->permit, 'en'))->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get(documentUrl($this->permit))->assertRedirect(route('login'));
});
