<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Jobs\GeneratePermitDocument;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitComment;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The office workflow (owner's decision, 2026-11-12): applicants do not sign
 * up or apply themselves. A request comes in by letter at a branch or the
 * head office; a Registrar records it with the letter and sends it on; a
 * Reviewer examines it and decides; a cashier takes or checks the payment and
 * records the receipt; an Issuer checks the paid request, issues and prints
 * the permit. The head office sees every branch and comments on requests.
 */

beforeEach(function () {
    config(['permits.two_step_approval' => false]);

    Notification::fake();
    Bus::fake([GeneratePermitDocument::class]);
    withFeeSchedule(150_000);

    $this->type = VehicleType::factory()->create();
});

/**
 * The Registrar's one-page form for a request brought in by letter.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function letterRequest(VehicleType $type, array $overrides = []): array
{
    $today = ValidityRules::today();

    return [
        'contact_name' => 'Abebe Kebede',
        'contact_email' => 'abebe@example.test',
        'locale' => 'am',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'phone' => '0911223344',
        'email' => 'office@abebe.test',
        'address' => 'Bole, Addis Ababa',
        'apply' => true,
        'submit' => true,
        'confirmed' => true,
        'vehicles' => [['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $type->id, 'owner_name' => 'አበበ ከበደ']],
        'permit_type' => 'full_day',
        'valid_from' => $today->addDays(3)->toDateString(),
        'valid_to' => $today->addDays(12)->toDateString(),
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        ...letterFields(),
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function deskPayment(Permit $permit, array $overrides = []): array
{
    return [
        'method' => 'bank_transfer',
        'receipt_number' => 'CBE-TRX-778899',
        'amount' => number_format($permit->fee_cents / 100, 2, '.', ''),
        'paid_at' => ValidityRules::today()->toDateString(),
        'note' => 'Checked on the bank portal.',
        ...$overrides,
    ];
}

it('takes a request from the letter to the printed permit', function () {
    $registrar = staffUser(Role::Registrar);
    $reviewer = staffUser(Role::Reviewer);
    $cashier = staffUser(Role::Cashier);
    $issuer = staffUser(Role::Issuer);
    $supervisor = staffUser(Role::Supervisor);

    // 1. The Registrar records the request with its letter and sends it on.
    $this->actingAs($registrar)
        ->post(route('admin.permits.store'), letterRequest($this->type))
        ->assertSessionHasNoErrors();

    $permit = Permit::query()->sole();

    expect($permit->status)->toBe(PermitStatus::Submitted)
        ->and($permit->letter_number)->toBe('ደ/45/2019')
        ->and($permit->letter_date?->toDateString())->toBe(ValidityRules::today()->subDay()->toDateString())
        ->and($permit->letter_file_path)->toStartWith('letters/');
    Storage::disk(config('permits.documents_disk'))->assertExists($permit->letter_file_path);

    // 2. The Reviewer examines it and decides alone.
    $this->actingAs($reviewer)->post(route('staff.permits.start-review', $permit))->assertSessionHas('success');
    $this->actingAs($reviewer)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and($permit->fee_cents)->toBe(150_000);

    // 3. The cashier records the payment at the desk.
    $this->actingAs($cashier)
        ->post(route('staff.permits.record-payment', $permit), deskPayment($permit))
        ->assertRedirect(route('staff.permits.show', $permit))
        ->assertSessionHas('success', __('permits.messages.payment_recorded'));

    $payment = Payment::query()->sole();

    expect($permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed)
        ->and($payment->status)->toBe(PaymentStatus::Verified)
        ->and($payment->method->value)->toBe('bank_transfer')
        ->and($payment->receipt_number)->toBe('CBE-TRX-778899')
        ->and($payment->submitted_by)->toBe($cashier->id)
        ->and($payment->verified_by)->toBe($cashier->id)
        ->and($payment->notes)->toBe('Checked on the bank portal.')
        ->and($permit->verification_token_hash)->toBeNull();

    // 4. The Issuer checks it, issues it and prints it.
    $this->actingAs($issuer)
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permit.can.issue', true)
            ->where('permit.letter.number', 'ደ/45/2019'));

    $this->actingAs($issuer)
        ->post(route('staff.permits.issue', $permit))
        ->assertSessionHas('success', __('permits.messages.issued'));

    expect($permit->refresh()->status)->toBe(PermitStatus::Issued)
        ->and($permit->issued_by)->toBe($issuer->id)
        ->and($permit->issued_at)->not->toBeNull()
        ->and($permit->verification_token_hash)->not->toBeNull();
    Bus::assertDispatched(GeneratePermitDocument::class, fn (GeneratePermitDocument $job): bool => $job->permitId === $permit->id);

    // 5. The head office comments on it.
    $this->actingAs($supervisor)
        ->post(route('staff.permits.comments.store', $permit), ['body' => '  ጥሩ ተሰርቷል  '])
        ->assertSessionHas('success');

    expect(PermitComment::query()->sole())
        ->body->toBe('ጥሩ ተሰርቷል')
        ->user_id->toBe($supervisor->id);

    $this->actingAs($reviewer)
        ->get(route('staff.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permit.comments', 1)
            ->where('permit.comments.0.author', $supervisor->name)
            ->where('permit.issued_by', $issuer->name));
});

describe('applicants have no pages of their own', function () {
    it('has no sign-up', function () {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.test', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertNotFound();

        expect(User::query()->where('email', 'x@example.test')->exists())->toBeFalse();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->missing('canRegister'));
    });

    it('gives an applicant no address to apply, pay or look at an application from', function (string $method, string $path) {
        $applicant = applicantWithOrganization();
        $permit = Permit::factory()->awaitingPayment()->create(['organization_id' => $applicant->organization_id]);

        $this->actingAs($applicant)
            ->call($method, str_replace('{permit}', $permit->id, $path))
            ->assertNotFound();
    })->with([
        ['GET', '/organization'],
        ['GET', '/vehicles'],
        ['GET', '/vehicles/create'],
        ['GET', '/permits'],
        ['GET', '/permits/create'],
        ['POST', '/permits'],
        ['GET', '/permits/{permit}'],
        ['POST', '/permits/{permit}/submit'],
        ['POST', '/permits/{permit}/payments'],
        ['GET', '/fee-estimate'],
    ]);

    it('does not let an applicant sign in, and ends a session left from before', function () {
        $applicant = applicantWithOrganization();
        $applicant->forceFill(['password' => 'A-strong-pass-2026!'])->save();

        $this->post(route('login.store'), ['email' => $applicant->email, 'password' => 'A-strong-pass-2026!'])
            ->assertSessionHasErrors(['email' => __('auth.no_sign_in')]);
        $this->assertGuest();

        foreach ([route('dashboard'), route('profile.edit'), route('staff.permits.index'), route('admin.permits.index')] as $url) {
            assertSignedOut($this->actingAs($applicant)->get($url));
        }
    });

    it('still lets a person in whose roles also give them office work', function () {
        $user = applicantWithOrganization();
        $user->assignRole(Role::Registrar->value);
        $user->forceFill(['password' => 'A-strong-pass-2026!'])->save();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'A-strong-pass-2026!'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    });

});

describe('the request letter', function () {
    it('is required with the request', function (string $field) {
        $this->actingAs(staffUser(Role::Registrar))
            ->post(route('admin.permits.store'), letterRequest($this->type, [$field => '']))
            ->assertSessionHasErrors($field);

        expect(Permit::query()->exists())->toBeFalse();
    })->with(['letter_number', 'letter_date', 'letter_file']);

    it('takes only a PDF or an image, dated today or earlier', function () {
        $this->actingAs(staffUser(Role::Registrar))
            ->post(route('admin.permits.store'), letterRequest($this->type, [
                'letter_file' => UploadedFile::fake()->create('letter.exe', 10, 'application/x-msdownload'),
                'letter_date' => ValidityRules::today()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['letter_file', 'letter_date']);

        expect(Storage::disk(config('permits.documents_disk'))->allFiles('letters'))->toBe([]);
    });

    it('is shown to the office and never to the applicant', function () {
        $this->actingAs(staffUser(Role::Registrar))->post(route('admin.permits.store'), letterRequest($this->type));
        $permit = Permit::query()->sole();
        $applicant = $permit->applicant;

        $this->actingAs(staffUser(Role::Reviewer))->get(route('staff.permits.letter', $permit))->assertOk();
        $this->actingAs(staffUser(Role::Registrar))->get(route('staff.permits.letter', $permit))->assertOk();
        assertSignedOut($this->actingAs($applicant)->get(route('staff.permits.letter', $permit)));
        $this->actingAs(staffUser(Role::TrafficOfficer))->get(route('staff.permits.letter', $permit))->assertNotFound();
    });
});

describe('the cashier\'s desk', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->awaitingPayment()->create(['fee_cents' => 150_000]);
        $this->cashier = staffUser(Role::Cashier);
    });

    it('lists the requests waiting for payment by default', function () {
        Permit::factory()->submitted()->create();

        $this->actingAs($this->cashier)
            ->get(route('staff.permits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 1)
                ->where('permits.data.0.id', $this->permit->id)
                ->where('permits.data.0.can.recordPayment', true));
    });

    it('takes the payment without a scan of the receipt', function () {
        $this->actingAs($this->cashier)
            ->post(route('staff.permits.record-payment', $this->permit), deskPayment($this->permit, ['method' => 'cash', 'note' => '']))
            ->assertSessionHasNoErrors();

        expect(Payment::query()->sole())
            ->receipt_file_path->toBeNull()
            ->notes->toBeNull();
    });

    it('refuses an amount that is not the fee', function () {
        $this->actingAs($this->cashier)
            ->post(route('staff.permits.record-payment', $this->permit), deskPayment($this->permit, ['amount' => '100']))
            ->assertSessionHasErrors('amount');

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(Payment::query()->exists())->toBeFalse();
    });

    it('refuses a receipt number already used', function () {
        Payment::factory()->verified()->create(['receipt_number' => 'CBE-TRX-778899']);

        $this->actingAs($this->cashier)
            ->post(route('staff.permits.record-payment', $this->permit), deskPayment($this->permit))
            ->assertSessionHasErrors('receipt_number');

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('is the cashier\'s job only', function (Role $role) {
        $this->actingAs(staffUser($role))
            ->post(route('staff.permits.record-payment', $this->permit), deskPayment($this->permit))
            ->assertForbidden();
    })->with([Role::Reviewer, Role::Issuer, Role::Supervisor]);

    it('keeps a branch cashier to their own branch', function () {
        $branch = Branch::factory()->create();
        $cashier = staffUser(Role::Cashier);
        $cashier->forceFill(['branch_id' => $branch->id])->save();
        // Their scope is that branch only.
        $cashier->branchScopes()->delete();
        $cashier->branchScopes()->create(['branch_id' => $branch->id, 'is_head_office' => false]);

        $this->actingAs($cashier->refresh())
            ->post(route('staff.permits.record-payment', $this->permit), deskPayment($this->permit))
            ->assertNotFound();
    });
});

describe('the Issuer\'s desk', function () {
    it('issues only what has been paid', function () {
        $permit = Permit::factory()->awaitingPayment()->create();

        $this->actingAs(staffUser(Role::Issuer))
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.issue', $permit))
            ->assertSessionHas('warning');

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
        Bus::assertNotDispatched(GeneratePermitDocument::class);
    });

    it('is the Issuer\'s job only', function (Role $role) {
        $permit = Permit::factory()->awaitingPayment()->create();
        $permit->forceFill(['status' => PermitStatus::PaymentConfirmed])->save();

        $this->actingAs(staffUser($role))->post(route('staff.permits.issue', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed);
    })->with([Role::Reviewer, Role::Approver, Role::Cashier, Role::Supervisor]);

    it('lists the paid requests by default', function () {
        $paid = Permit::factory()->awaitingPayment()->create();
        $paid->forceFill(['status' => PermitStatus::PaymentConfirmed])->save();
        Permit::factory()->awaitingPayment()->create();

        $this->actingAs(staffUser(Role::Issuer))
            ->get(route('staff.permits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 1)
                ->where('permits.data.0.id', $paid->id));
    });
});

describe('the head office', function () {
    it('comments, and only the head office does', function () {
        $permit = Permit::factory()->submitted()->create();

        $this->actingAs(staffUser(Role::Reviewer))
            ->post(route('staff.permits.comments.store', $permit), ['body' => 'x'])
            ->assertForbidden();

        $this->actingAs(staffUser(Role::Supervisor))
            ->post(route('staff.permits.comments.store', $permit), ['body' => ''])
            ->assertSessionHasErrors('body');

        expect(PermitComment::query()->exists())->toBeFalse();
    });

    it('sees every branch\'s requests and figures', function () {
        $branch = Branch::factory()->create();
        Permit::factory()->submitted()->create(['branch_id' => $branch->id]);
        Permit::factory()->submitted()->create(['branch_id' => null]);

        $this->actingAs(staffUser(Role::Supervisor))
            ->get(route('staff.permits.index'))
            ->assertInertia(fn (Assert $page) => $page->has('permits.data', 2));

        $this->actingAs(staffUser(Role::Supervisor))->get(route('staff.reports.index'))->assertOk();
    });
});
