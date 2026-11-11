<?php

use App\Enums\PaymentStatus;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * A receipt pays for one permit (spec §4.5). Enforced by the form, the Action
 * and a partial unique index; a rejected payment gives its number back. The
 * error names the receipt and nothing about whoever used it.
 */

beforeEach(function () {
    Storage::fake('documents');

    $this->applicant = applicantWithOrganization();
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $this->permit = Permit::factory()->forVehicles($vehicle)->awaitingPayment()->create(['fee_cents' => 100_000]);

    $this->otherOrganization = Organization::factory()->create(['name_am' => 'ሌላ ድርጅት', 'name_en' => 'Rival Haulage']);
    $this->otherPermit = Permit::factory()
        ->forVehicles(Vehicle::factory()->for($this->otherOrganization)->create())
        ->paymentSubmitted()
        ->create(['permit_number' => 'TMA-HV-2019-777777']);
});

/**
 * @return array<string, mixed>
 */
function receiptForm(string $receipt): array
{
    return [
        'method' => 'bank_deposit',
        'receipt_number' => $receipt,
        'amount' => '1000',
        'paid_at' => now()->subDay()->toDateString(),
        'receipt_file' => UploadedFile::fake()->createWithContent('r.pdf', "%PDF-1.4\n0\n%%EOF\n"),
    ];
}

it('refuses a receipt already used by another organization without saying who used it', function (string $typed) {
    Payment::factory()->forPermit($this->otherPermit)->create(['receipt_number' => 'FS-228740']);

    $response = $this->actingAs($this->applicant)
        ->post(route('permits.payments.store', $this->permit), receiptForm($typed))
        ->assertSessionHasErrors('receipt_number');

    $message = session('errors')->first('receipt_number');

    expect($message)->toContain('FS-228740')
        ->not->toContain('Rival')
        ->not->toContain('ሌላ ድርጅት')
        ->not->toContain('777777')
        ->not->toContain($this->otherPermit->id)
        ->and(Payment::query()->where('permit_id', $this->permit->id)->count())->toBe(0);

    $response->assertRedirect();
})->with(['FS-228740', 'fs-228740', ' FS - 228740 ', "fs\u{2013}228740"]);

it('stores the receipt number in its canonical form', function () {
    $this->actingAs($this->applicant)
        ->post(route('permits.payments.store', $this->permit), receiptForm(' fs 228 740 '))
        ->assertSessionHasNoErrors();

    expect(Payment::query()->where('permit_id', $this->permit->id)->value('receipt_number'))->toBe('FS228740');
});

it('accepts a receipt number whose only earlier use was rejected', function () {
    Payment::factory()->forPermit($this->otherPermit)->rejected()->create(['receipt_number' => 'FS228740']);

    $this->actingAs($this->applicant)
        ->post(route('permits.payments.store', $this->permit), receiptForm('FS228740'))
        ->assertSessionHasNoErrors();

    expect(Payment::query()->where('receipt_number', 'FS228740')->count())->toBe(2);
});

it('refuses a receipt that is already verified', function () {
    Payment::factory()->forPermit($this->otherPermit)->verified()->create(['receipt_number' => 'FS228740']);

    $this->actingAs($this->applicant)
        ->post(route('permits.payments.store', $this->permit), receiptForm('FS228740'))
        ->assertSessionHasErrors('receipt_number');
});

describe('database', function () {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function insertPayment(Permit $permit, string $receipt, string $status = 'submitted'): void
    {
        DB::table('payments')->insert([
            'id' => strtolower((string) Str::ulid()),
            'permit_id' => $permit->id,
            'method' => 'bank_deposit',
            'receipt_number' => $receipt,
            'amount_cents' => 100_000,
            'paid_at' => '2026-11-10',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    it('refuses two live payments with one receipt number', function (string $first, string $second) {
        insertPayment($this->otherPermit, 'FS228740', $first);

        expect(fn () => insertPayment($this->permit, 'FS228740', $second))
            ->toThrow(fn (QueryException $e) => expect($e->getCode())->toBe('23505'));
    })->with([
        ['submitted', 'submitted'],
        ['verified', 'submitted'],
        ['submitted', 'verified'],
    ]);

    it('allows the number again after a rejection', function () {
        insertPayment($this->otherPermit, 'FS228740', PaymentStatus::Rejected->value);
        insertPayment($this->otherPermit, 'FS228740', PaymentStatus::Rejected->value);
        insertPayment($this->permit, 'FS228740');

        expect(Payment::query()->where('receipt_number', 'FS228740')->count())->toBe(3);
    });
});
