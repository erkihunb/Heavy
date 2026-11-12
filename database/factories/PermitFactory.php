<?php

namespace Database\Factories;

use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Recommendation;
use App\Enums\VehicleRole;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A permit with one primary vehicle of its own organization (created on the
 * fly unless given with {@see forVehicles()}), and the vehicle rows written
 * the way the Actions leave them for the permit's status. No history rows are
 * written: tests that care about history go through the Actions.
 *
 * @extends Factory<Permit>
 */
class PermitFactory extends Factory
{
    private ?Vehicle $primaryVehicle = null;

    /** @var list<Vehicle> */
    private array $trailerVehicles = [];

    private bool $attachVehicles = true;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = ValidityRules::today();

        return [
            'organization_id' => Organization::factory(),
            'applicant_id' => User::factory(),
            'permit_type' => PermitType::FullDay,
            'status' => PermitStatus::Draft,
            'reason' => fake()->sentence(),
            'valid_from' => $today->addDays(7)->toDateString(),
            'valid_to' => $today->addDays(36)->toDateString(),
        ];
    }

    /**
     * Runs on the factory that actually creates the permit, so it sees the
     * vehicle choices made on the way (a `configure()` callback would be
     * bound to the first instance).
     *
     * @param  Collection<int, Permit>  $instances
     */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        $instances->each(fn (Permit $permit) => $this->attachVehiclesTo($permit));

        parent::callAfterCreating($instances, $parent);
    }

    private function attachVehiclesTo(Permit $permit): void
    {
        if (! $this->attachVehicles) {
            return;
        }

        $primary = $this->primaryVehicle
            ?? Vehicle::factory()->create(['organization_id' => $permit->organization_id]);

        $row = fn (Vehicle $vehicle, VehicleRole $role): array => [
            'permit_id' => $permit->id,
            'vehicle_id' => $vehicle->id,
            'role' => $role->value,
            'period_from' => $permit->valid_from->toDateString(),
            'period_to' => $permit->valid_to->toDateString(),
            'is_blocking' => $permit->status->blocksVehicles(),
        ];

        DB::table('permit_vehicles')->insert([
            $row($primary, VehicleRole::Primary),
            ...array_map(fn (Vehicle $trailer): array => $row($trailer, VehicleRole::Trailer), $this->trailerVehicles),
        ]);
    }

    /**
     * Put these vehicles on the permit; it belongs to the primary vehicle's organization.
     */
    public function forVehicles(Vehicle $primary, Vehicle ...$trailers): static
    {
        $factory = $this->state(['organization_id' => $primary->organization_id]);
        $factory->primaryVehicle = $primary;
        $factory->trailerVehicles = array_values($trailers);

        return $factory;
    }

    public function withoutVehicles(): static
    {
        $factory = $this->newInstance();
        $factory->attachVehicles = false;

        return $factory;
    }

    public function validBetween(DateTimeInterface|string $from, DateTimeInterface|string $to): static
    {
        return $this->state([
            'valid_from' => is_string($from) ? $from : $from->format('Y-m-d'),
            'valid_to' => is_string($to) ? $to : $to->format('Y-m-d'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(['status' => PermitStatus::Draft]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PermitStatus::Submitted,
            'submitted_at' => now()->subHours(2),
        ]);
    }

    public function underReview(): static
    {
        return $this->submitted()->state(['status' => PermitStatus::UnderReview]);
    }

    /**
     * Under review, with a Reviewer's recommendation recorded.
     */
    public function recommended(Recommendation $recommendation = Recommendation::Approve, ?User $reviewer = null): static
    {
        return $this->underReview()->state(fn (): array => [
            'recommendation' => $recommendation,
            'recommended_by' => $reviewer->id ?? User::factory(),
            'recommended_at' => now()->subHour(),
            'recommendation_note' => $recommendation === Recommendation::Reject ? fake()->sentence() : null,
        ]);
    }

    public function changesRequested(): static
    {
        return $this->submitted()->state(['status' => PermitStatus::ChangesRequested]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(['status' => PermitStatus::Rejected]);
    }

    public function awaitingPayment(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => PermitStatus::AwaitingPayment,
            'permit_number' => 'AACRA-2019-'.fake()->unique()->numerify('9#####'),
            'approved_by' => User::factory(),
            'approved_at' => now()->subHour(),
            // Santim; the fee is copied onto the permit at approval.
            'fee_cents' => 100_000,
        ]);
    }

    public function paymentSubmitted(): static
    {
        return $this->awaitingPayment()->state(['status' => PermitStatus::PaymentSubmitted]);
    }

    /**
     * Issued, with a validity that has not started yet (the default dates).
     */
    public function issued(): static
    {
        return $this->awaitingPayment()->state(fn (): array => [
            'status' => PermitStatus::Issued,
            'issued_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Issued and valid today: its effective status is "active".
     */
    public function active(): static
    {
        $today = ValidityRules::today();

        return $this->issued()->validBetween($today->subDays(3), $today->addDays(10));
    }

    /**
     * Marked expired by the scheduled job; its validity is in the past.
     */
    public function expired(): static
    {
        $today = ValidityRules::today();

        return $this->issued()
            ->validBetween($today->subDays(40), $today->subDays(10))
            ->state(['status' => PermitStatus::Expired]);
    }

    public function revoked(): static
    {
        return $this->issued()->state(fn (): array => [
            'status' => PermitStatus::Revoked,
            'revoked_by' => User::factory(),
            'revoked_at' => now(),
            'revocation_reason' => fake()->sentence(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(['status' => PermitStatus::Withdrawn]);
    }

    /**
     * Carry the vehicle choices over to the copies the fluent methods make.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->primaryVehicle = $this->primaryVehicle;
        $factory->trailerVehicles = $this->trailerVehicles;
        $factory->attachVehicles = $this->attachVehicles;

        return $factory;
    }
}
