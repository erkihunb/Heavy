<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\User;
use App\Models\VehicleType;
use App\Notifications\LetterNotification;
use App\Notifications\PermitApproved;
use App\Notifications\PermitIssued;
use App\Notifications\PermitRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * One request letter asking for permits for several vehicles: each vehicle
 * gets a permit of its own (number, fee, certificate, QR code); the letter is
 * reviewed, decided — some vehicles approved, the rest refused — paid for
 * with one receipt and issued together.
 */

beforeEach(function () {
    Notification::fake();
    withFeeSchedule();
    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create();
    $this->otherType = VehicleType::factory()->create();
});

/**
 * The one-page form for a new organization whose letter asks for three vehicles.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function letterForm(array $overrides = []): array
{
    $types = VehicleType::query()->orderBy('id')->pluck('id');

    return withFeeRate([
        'contact_name' => 'Abebe Kebede',
        'contact_email' => 'abebe@example.test',
        'locale' => 'am',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'tin' => '0012345678',
        'phone' => '0911223344',
        'email' => 'office@abebe.test',
        'apply' => true,
        'submit' => true,
        'vehicles' => [
            ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $types[0], 'owner_name' => 'አበበ ከበደ'],
            ['plate' => 'ኢት-03-40135', 'vehicle_type_id' => $types[0], 'owner_name' => 'አበበ ከበደ'],
        ],
        'more' => [
            ['vehicles' => [['plate' => 'ኢት-03-50001', 'vehicle_type_id' => $types[1], 'owner_name' => 'አበበ ከበደ']]],
            ['vehicles' => [
                ['plate' => 'ኢት-03-50002', 'vehicle_type_id' => $types[0], 'owner_name' => 'ሰላም ተስፋዬ'],
                ['plate' => '', 'vehicle_type_id' => '', 'owner_name' => ''],
            ]],
        ],
        'permit_type' => PermitType::FullDay->value,
        'valid_from' => ValidityRules::today()->addDays(3)->toDateString(),
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        ...letterFields(),
        ...$overrides,
    ]);
}

/**
 * A letter registered through the form, with its permits submitted.
 */
function registeredLetter(): RequestLetter
{
    test()->actingAs(test()->registrar)
        ->post(route('admin.permits.store'), letterForm())
        ->assertSessionHasNoErrors();

    return RequestLetter::query()->sole();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function letterPayment(array $overrides = []): array
{
    return [
        'method' => 'bank_transfer',
        'receipt_number' => 'CBE-778899',
        'amount' => '2000.00',
        'paid_at' => ValidityRules::today()->toDateString(),
        'receipt_file' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ...$overrides,
    ];
}

describe('registering', function () {
    it('gives each vehicle of the letter a request of its own, grouped under the letter', function () {
        $response = $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), letterForm())
            ->assertSessionHasNoErrors();

        $letter = RequestLetter::query()->sole();
        $permits = Permit::query()->with('vehicles')->orderBy('created_at')->orderBy('id')->get();

        $response->assertRedirect(route('letters.show', $letter));

        expect($permits)->toHaveCount(3)
            ->and($permits->pluck('request_letter_id')->unique()->all())->toBe([$letter->id])
            ->and($permits->pluck('status')->unique()->all())->toBe([PermitStatus::Submitted])
            // The scan is stored once and shared.
            ->and($permits->pluck('letter_file_path')->unique())->toHaveCount(1)
            ->and($letter->letter_file_path)->toBe($permits->first()->letter_file_path)
            ->and($permits->pluck('letter_number')->unique()->all())->toBe(['ደ/45/2019'])
            // Same terms for every vehicle.
            ->and($permits->map(fn (Permit $permit): string => $permit->valid_from->toDateString().'/'.$permit->valid_to->toDateString())->unique())->toHaveCount(1)
            ->and($permits[0]->vehicles)->toHaveCount(2)
            ->and($permits[1]->vehicles->pluck('vehicle_type_id')->all())->toBe([$this->otherType->id])
            ->and($permits[2]->vehicles)->toHaveCount(1);

        Storage::disk(config('permits.documents_disk'))->assertExists($letter->letter_file_path);
    });

    it('refuses a plate given twice in the letter, saving nothing', function () {
        $form = letterForm();
        $form['more'][0]['vehicles'][0]['plate'] = 'ኢት-03-40134';

        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), $form)
            ->assertSessionHasErrors('more.0.vehicles.0.plate');

        expect(Permit::query()->count())->toBe(0)
            ->and(RequestLetter::query()->count())->toBe(0);
    });

    it('refuses a vehicle whose type has no fee for the letter\'s days', function () {
        FeeSchedule::query()->delete();
        FeeSchedule::factory()->forType(PermitType::FullDay)->forVehicleType($this->type)->amount(50_000)->create();

        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), letterForm())
            ->assertSessionHasErrors('more.0.vehicles.0.vehicle_type_id');

        expect(Permit::query()->count())->toBe(0);
    });

    it('keeps a request for one vehicle as before', function () {
        $this->actingAs($this->registrar)
            ->post(route('admin.permits.store'), letterForm(['more' => []]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.permits.show', Permit::query()->sole()));
    });
});

describe('deciding', function () {
    beforeEach(function () {
        $this->letter = registeredLetter();
        $this->permits = $this->letter->permits()->get();
        $this->reviewer = staffUser(Role::Reviewer);
        $this->approver = staffUser(Role::Approver);
    });

    it('shows the letter and its vehicles to the office', function () {
        $this->actingAs($this->approver)
            ->get(route('letters.show', $this->letter))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/letters/show')
                ->has('permits', 3)
                ->where('summary.total', 3)
                ->where('can.decide', true)
                ->where('can.recordPayment', false));
    });

    it('lists every vehicle of the letter on each permit page', function () {
        $permit = $this->permits[1];

        foreach (['staff.permits.decision', 'staff.permits.show'] as $route) {
            $this->actingAs($this->approver)
                ->get(route($route, $permit))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('letterVehicles', 3)
                    ->where('letterVehicles.1.id', $permit->id)
                    ->where('letterVehicles.1.current', true)
                    ->where('letterVehicles.0.current', false));
        }

        // A permit asked for alone lists nothing.
        $alone = Permit::factory()->submitted()->create();

        $this->actingAs($this->approver)
            ->get(route('staff.permits.decision', $alone))
            ->assertInertia(fn (Assert $page) => $page->where('letterVehicles', []));
    });

    it('lets the approver approve some vehicles and refuse the rest, telling the organization once each', function () {
        $ids = $this->permits->pluck('id')->all();

        $this->actingAs($this->reviewer)
            ->post(route('letters.recommend', $this->letter), ['permits' => $ids, 'recommendation' => 'approve', 'note' => 'Documents in order.'])
            ->assertSessionHasNoErrors();

        expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::UnderReview]);

        $this->actingAs($this->approver)
            ->post(route('letters.approve', $this->letter), ['permits' => array_slice($ids, 0, 2)])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->approver)
            ->post(route('letters.reject', $this->letter), ['permits' => [$ids[2]], 'note' => 'Only two vehicles this month.'])
            ->assertSessionHasNoErrors();

        $permits = Permit::query()->whereIn('id', $ids)->get()->keyBy('id');

        expect($permits[$ids[0]]->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permits[$ids[1]]->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permits[$ids[2]]->status)->toBe(PermitStatus::Rejected)
            // Each approved vehicle has its own number and fee.
            ->and($permits[$ids[0]]->permit_number)->not->toBe($permits[$ids[1]]->permit_number)
            ->and($permits[$ids[0]]->fee_cents)->toBe(100_000);

        $applicant = User::query()->where('email', 'abebe@example.test')->sole();
        Notification::assertNotSentTo($applicant, PermitApproved::class);
        Notification::assertNotSentTo($applicant, PermitRejected::class);
        Notification::assertSentTo($applicant, LetterNotification::class, fn (LetterNotification $n): bool => $n->what === 'approved' && $n->count === 2 && $n->totalCents === 200_000);
        Notification::assertSentTo($applicant, LetterNotification::class, fn (LetterNotification $n): bool => $n->what === 'rejected' && $n->count === 1);
    });

    it('keeps the approval rules: the reviewer cannot approve what they reviewed', function () {
        $ids = $this->permits->pluck('id')->all();
        $both = staffUser(Role::Reviewer);
        $both->assignRole(Role::Approver->value);

        $this->actingAs($both)
            ->post(route('letters.recommend', $this->letter), ['permits' => $ids, 'recommendation' => 'approve', 'note' => 'Fine.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($both)
            ->post(route('letters.approve', $this->letter), ['permits' => $ids])
            ->assertSessionHas('warning');

        // Nothing was approved: one refused vehicle stops the lot.
        expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::UnderReview]);
    });

    it('needs a reason to refuse, and the vehicles chosen must be the letter\'s', function () {
        $this->actingAs($this->approver)
            ->post(route('letters.reject', $this->letter), ['permits' => [$this->permits[0]->id], 'note' => ''])
            ->assertSessionHasErrors('note');

        $other = Permit::factory()->submitted()->create();

        $this->actingAs($this->approver)
            ->post(route('letters.approve', $this->letter), ['permits' => [$other->id]])
            ->assertSessionHasErrors('permits');
    });

    it('refuses the decision to someone without the permission', function () {
        $this->actingAs(staffUser(Role::Cashier))
            ->post(route('letters.approve', $this->letter), ['permits' => $this->permits->pluck('id')->all()])
            ->assertForbidden();

        expect(Permit::query()->pluck('status')->unique()->all())->toBe([PermitStatus::Submitted]);
    });
});

describe('paying and issuing', function () {
    beforeEach(function () {
        $this->letter = registeredLetter();
        $ids = $this->letter->permits()->pluck('id')->all();
        $this->ids = $ids;

        $this->actingAs(staffUser(Role::Reviewer))
            ->post(route('letters.recommend', $this->letter), ['permits' => $ids, 'recommendation' => 'approve', 'note' => 'Fine.']);
        $this->actingAs(staffUser(Role::Approver))
            ->post(route('letters.approve', $this->letter), ['permits' => array_slice($ids, 0, 2)]);
        $this->actingAs(staffUser(Role::Approver))
            ->post(route('letters.reject', $this->letter), ['permits' => [$ids[2]], 'note' => 'Not now.']);

        $this->cashier = staffUser(Role::Cashier);
    });

    it('takes no payment for one vehicle of the letter on its own', function () {
        $permit = Permit::query()->findOrFail($this->ids[0]);

        $this->actingAs($this->cashier)
            ->post(route('staff.permits.record-payment', $permit), letterPayment(['amount' => '1000.00']))
            ->assertForbidden();

        expect(Payment::query()->count())->toBe(0)
            ->and($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('lists the letter once among the payments to collect, with its total', function () {
        $first = Permit::query()->whereIn('id', array_slice($this->ids, 0, 2))->orderBy('id')->firstOrFail();

        $this->actingAs($this->cashier)
            ->get(route('staff.permits.index', ['status' => 'awaiting_payment']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 1)
                ->where('permits.data.0.id', $first->id)
                ->where("letterPayments.{$first->id}.vehicles", 2)
                ->where("letterPayments.{$first->id}.url", route('letters.show', $this->letter)));
    });

    it('takes one receipt for the total of the approved vehicles', function () {
        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment())
            ->assertSessionHasNoErrors();

        $payments = Payment::query()->get();

        expect($payments)->toHaveCount(2)
            ->and($payments->pluck('receipt_number')->unique()->all())->toBe(['CBE-778899'])
            ->and($payments->pluck('amount_cents')->all())->toBe([100_000, 100_000])
            ->and($payments->pluck('request_letter_id')->unique()->all())->toBe([$this->letter->id])
            ->and(Permit::query()->whereIn('id', array_slice($this->ids, 0, 2))->pluck('status')->unique()->all())->toBe([PermitStatus::PaymentConfirmed]);
    });

    it('refuses an amount that is not the total', function () {
        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment(['amount' => '1000.00']))
            ->assertSessionHasErrors('amount');

        expect(Payment::query()->count())->toBe(0);
    });

    it('refuses a receipt that already paid for something else', function () {
        Payment::factory()->create(['receipt_number' => 'CBE-778899']);

        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment())
            ->assertSessionHasErrors('receipt_number');

        expect(Payment::query()->whereNotNull('request_letter_id')->count())->toBe(0);
    });

    it('issues no vehicle of the letter on its own', function () {
        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment());

        $permit = Permit::query()->findOrFail($this->ids[0]);

        $this->actingAs(staffUser(Role::Issuer))
            ->post(route('staff.permits.issue', $permit))
            ->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::PaymentConfirmed);
    });

    it('lists the paid letter once for the Issuer', function () {
        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment());

        $first = Permit::query()->whereIn('id', array_slice($this->ids, 0, 2))->orderBy('id')->firstOrFail();

        $this->actingAs(staffUser(Role::Issuer))
            ->get(route('staff.permits.index', ['status' => 'payment_confirmed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 1)
                ->where("letterPayments.{$first->id}.vehicles", 2)
                ->where("letterPayments.{$first->id}.stage", 'payment_confirmed'));
    });

    it('issues every paid vehicle with its own certificate, and hands them out together', function () {
        $this->actingAs($this->cashier)
            ->post(route('letters.payment', $this->letter), letterPayment());

        $this->actingAs(staffUser(Role::Issuer))
            ->post(route('letters.issue', $this->letter))
            ->assertSessionHasNoErrors();

        $issued = Permit::query()->where('status', PermitStatus::Issued)->get();

        expect($issued)->toHaveCount(2)
            ->and($issued->pluck('verification_token_hash')->filter()->unique())->toHaveCount(2)
            ->and($issued->pluck('document_path')->filter())->toHaveCount(2);

        $applicant = User::query()->where('email', 'abebe@example.test')->sole();
        Notification::assertNotSentTo($applicant, PermitIssued::class);
        Notification::assertSentTo($applicant, LetterNotification::class, fn (LetterNotification $n): bool => $n->what === 'issued' && $n->count === 2);

        $response = $this->actingAs(staffUser(Role::Issuer))
            ->get(route('letters.documents', [$this->letter, 'am']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => (string) $zip->getNameIndex($i))->sort()->values()->all();

        expect($names)->toBe($issued->map(fn (Permit $permit): string => "{$permit->permit_number}-am.pdf")->sort()->values()->all());
    });
});
