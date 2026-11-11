<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;

function organizationPayload(array $overrides = []): array
{
    return [
        'name_am' => 'አበበ ትራንስፖርት ኃ/የተ/የግ/ማ',
        'name_en' => 'Abebe Transport PLC',
        'tin' => '0012345678',
        'phone' => '0911234567',
        'email' => 'office@abebe-transport.example',
        'address' => 'ቦሌ ክፍለ ከተማ፣ ወረዳ 03',
        ...$overrides,
    ];
}

describe('viewing', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('organization.show'))->assertRedirect(route('login'));
    });

    it('shows an applicant without an organization the empty profile form', function () {
        $this->actingAs(userWithRole(Role::Applicant))
            ->get(route('organization.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/organization')
                ->where('organization', null)
                ->where('canUpdate', true));
    });

    it('shows a member their organization with whitelisted fields only', function () {
        $owner = applicantWithOrganization();

        $this->actingAs($owner)
            ->get(route('organization.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.name_am', $owner->organization->name_am)
                ->where('organization', fn ($shared) => collect($shared)->keys()->sort()->values()->all()
                    === ['address', 'email', 'id', 'name', 'name_am', 'name_en', 'phone', 'tin'])
                ->where('canUpdate', true));
    });

    it('lets a member who is not the owner view but not edit', function () {
        $owner = applicantWithOrganization();
        $member = userWithRole(Role::Applicant);
        $member->forceFill(['organization_id' => $owner->organization_id])->save();

        $this->actingAs($member)
            ->get(route('organization.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('organization.id', $owner->organization_id)
                ->where('canUpdate', false));
    });

    it('forbids staff, who have no organization profile', function (Role $role) {
        $this->actingAs(userWithRole($role, twoFactor: true))
            ->get(route('organization.show'))
            ->assertForbidden();
    })->with(fn () => Role::staff());
});

describe('creating', function () {
    it('creates the organization and makes the applicant its owner', function () {
        $user = userWithRole(Role::Applicant);

        $this->actingAs($user)
            ->post(route('organization.store'), organizationPayload())
            ->assertRedirect(route('organization.show'))
            ->assertSessionHas('success');

        $organization = Organization::sole();

        expect($organization->name_am)->toBe('አበበ ትራንስፖርት ኃ/የተ/የግ/ማ')
            ->and($organization->name_en)->toBe('Abebe Transport PLC')
            ->and($organization->tin)->toBe('0012345678')
            ->and($organization->email)->toBe('office@abebe-transport.example')
            ->and($organization->address)->toBe('ቦሌ ክፍለ ከተማ፣ ወረዳ 03')
            ->and($organization->created_by)->toBe($user->id)
            ->and($user->refresh()->organization_id)->toBe($organization->id);
    });

    it('stores the phone number in international format', function () {
        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('organization.store'), organizationPayload(['phone' => '(011) 551-2345']));

        expect(Organization::sole()->phone)->toBe('+251115512345');
    });

    it('treats the English name and the TIN as optional', function () {
        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('organization.store'), organizationPayload(['name_en' => '', 'tin' => '']))
            ->assertSessionHasNoErrors();

        expect(Organization::sole()->name_en)->toBeNull()
            ->and(Organization::sole()->tin)->toBeNull();
    });

    it('allows several organizations without a TIN', function () {
        Organization::factory()->create(['tin' => null]);

        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('organization.store'), organizationPayload(['tin' => null]))
            ->assertSessionHasNoErrors();

        expect(Organization::count())->toBe(2);
    });

    it('rejects invalid input', function (array $overrides, string $field) {
        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('organization.store'), organizationPayload($overrides))
            ->assertSessionHasErrors($field);

        expect(Organization::count())->toBe(0);
    })->with([
        'missing Amharic name' => [['name_am' => ''], 'name_am'],
        'name too long' => [['name_am' => str_repeat('አ', 256)], 'name_am'],
        'TIN with letters' => [['tin' => '00123ABC78'], 'tin'],
        'TIN too short' => [['tin' => '123456789'], 'tin'],
        'TIN too long' => [['tin' => '12345678901'], 'tin'],
        'missing phone' => [['phone' => ''], 'phone'],
        'phone that is not Ethiopian' => [['phone' => '+14155552671'], 'phone'],
        'missing email' => [['email' => ''], 'email'],
        'malformed email' => [['email' => 'not-an-email'], 'email'],
        'missing address' => [['address' => ''], 'address'],
        'address too long' => [['address' => str_repeat('a', 501)], 'address'],
    ]);

    it('rejects a TIN that another organization already uses', function () {
        Organization::factory()->create(['tin' => '0012345678']);

        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('organization.store'), organizationPayload())
            ->assertSessionHasErrors('tin');

        expect(Organization::count())->toBe(1);
    });

    it('enforces TIN uniqueness in the database as well', function () {
        Organization::factory()->create(['tin' => '0012345678']);

        expect(fn () => Organization::factory()->create(['tin' => '0012345678']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not let an applicant create a second organization', function () {
        $owner = applicantWithOrganization();

        $this->actingAs($owner)
            ->post(route('organization.store'), organizationPayload(['tin' => '0099999999']))
            ->assertForbidden();

        expect(Organization::count())->toBe(1);
    });

    it('ignores an owner or id supplied in the request', function () {
        $other = User::factory()->create();
        $user = userWithRole(Role::Applicant);

        $this->actingAs($user)->post(route('organization.store'), organizationPayload([
            'created_by' => $other->id,
            'id' => 999,
        ]));

        expect(Organization::sole()->created_by)->toBe($user->id)
            ->and(Organization::sole()->id)->not->toBe(999);
    });

    it('forbids staff', function (Role $role) {
        $this->actingAs(userWithRole($role, twoFactor: true))
            ->post(route('organization.store'), organizationPayload())
            ->assertForbidden();

        expect(Organization::count())->toBe(0);
    })->with(fn () => Role::staff());

    it('requires a verified email address', function () {
        $user = User::factory()->unverified()->create();
        $user->assignRole(Role::Applicant->value);

        $this->actingAs($user)
            ->post(route('organization.store'), organizationPayload())
            ->assertRedirect(route('verification.notice'));

        expect(Organization::count())->toBe(0);
    });
});

describe('updating', function () {
    it('lets the owner update the profile', function () {
        $owner = applicantWithOrganization();

        $this->actingAs($owner)
            ->put(route('organization.update'), organizationPayload(['name_am' => 'አዲስ ስም', 'phone' => '0922000000']))
            ->assertRedirect(route('organization.show'))
            ->assertSessionHas('success');

        $organization = $owner->organization->refresh();

        expect($organization->name_am)->toBe('አዲስ ስም')
            ->and($organization->phone)->toBe('+251922000000')
            ->and($organization->created_by)->toBe($owner->id);
    });

    it('lets the owner keep their own TIN', function () {
        $owner = applicantWithOrganization(['tin' => '0012345678']);

        $this->actingAs($owner)
            ->put(route('organization.update'), organizationPayload(['tin' => '0012345678']))
            ->assertSessionHasNoErrors();
    });

    it('rejects a TIN that belongs to another organization', function () {
        Organization::factory()->create(['tin' => '0077777777']);
        $owner = applicantWithOrganization(['tin' => '0012345678']);

        $this->actingAs($owner)
            ->put(route('organization.update'), organizationPayload(['tin' => '0077777777']))
            ->assertSessionHasErrors('tin');

        expect($owner->organization->refresh()->tin)->toBe('0012345678');
    });

    it('does not let a member who is not the owner update the profile', function () {
        $owner = applicantWithOrganization();
        $member = userWithRole(Role::Applicant);
        $member->forceFill(['organization_id' => $owner->organization_id])->save();
        $originalName = $owner->organization->name_am;

        $this->actingAs($member)
            ->put(route('organization.update'), organizationPayload(['name_am' => 'ተቀይሯል']))
            ->assertForbidden();

        expect($owner->organization->refresh()->name_am)->toBe($originalName);
    });

    it('never touches another organization', function () {
        $other = Organization::factory()->create();
        $owner = applicantWithOrganization();

        $this->actingAs($owner)
            ->put(route('organization.update'), organizationPayload(['name_am' => 'ተቀይሯል', 'id' => $other->id]));

        expect($other->refresh()->name_am)->not->toBe('ተቀይሯል');
    });

    it('forbids an applicant who has no organization yet', function () {
        $this->actingAs(userWithRole(Role::Applicant))
            ->put(route('organization.update'), organizationPayload())
            ->assertForbidden();
    });
});
