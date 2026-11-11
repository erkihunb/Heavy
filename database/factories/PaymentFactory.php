<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * A receipt waiting for verification, for a permit whose payment was submitted.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permit_id' => Permit::factory()->paymentSubmitted(),
            'method' => PaymentMethod::BankDeposit,
            'receipt_number' => 'FS'.fake()->unique()->numerify('########'),
            'amount_cents' => 100_000,
            'paid_at' => now()->subDay()->toDateString(),
            'receipt_file_path' => null,
            'status' => PaymentStatus::Submitted,
            'submitted_by' => User::factory(),
        ];
    }

    public function forPermit(Permit $permit): static
    {
        return $this->state(fn (): array => [
            'permit_id' => $permit->id,
            'amount_cents' => $permit->fee_cents ?? 100_000,
        ]);
    }

    public function verified(?User $cashier = null): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Verified,
            'verified_by' => $cashier->id ?? User::factory(),
            'verified_at' => now()->subMinutes(10),
        ]);
    }

    public function rejected(?User $cashier = null, string $note = 'Receipt is not readable.'): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Rejected,
            'verified_by' => $cashier->id ?? User::factory(),
            'verified_at' => now()->subMinutes(10),
            'notes' => $note,
        ]);
    }
}
