<?php

namespace App\Actions\Users;

use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Vehicles\RegisterVehicle;
use App\Data\OrganizationData;
use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class RegisterWalkInApplication
{
    public function __construct(
        private readonly RegisterApplicant $registerApplicant,
        private readonly RegisterVehicle $registerVehicle,
        private readonly CreateDraftPermit $createDraftPermit,
        private readonly SubmitPermit $submitPermit,
    ) {}

    /**
     * The office's one-page form (as the paper form): the applicant and their
     * organization, the vehicle and its trailers, and the permit asked for,
     * saved together by the same Actions as online — all of it or nothing.
     * No payment is taken here: the fee is paid once the permit is approved.
     * The applicant's account is a record only: nobody signs in with it.
     *
     * @param  list<VehicleData>  $vehicles  the primary vehicle first, then the trailers
     * @return array{applicant: User, permit: Permit|null}
     */
    public function handle(
        string $name,
        string $email,
        string $locale,
        OrganizationData $organization,
        array $vehicles,
        ?WalkInPermitData $permit,
        User $registrar,
    ): array {
        [$applicant, $application] = DB::transaction(function () use ($name, $email, $locale, $organization, $vehicles, $permit, $registrar): array {
            $applicant = $this->registerApplicant->handle($name, $email, $locale, $organization, $registrar);

            /** @var Organization $profile */
            $profile = $applicant->organization;

            $ids = array_map(
                fn (VehicleData $vehicle): string => $this->registerVehicle->handle($profile, $vehicle)->id,
                $vehicles,
            );

            if ($permit === null || $ids === []) {
                return [$applicant, null];
            }

            $application = $this->createDraftPermit->handle(
                $profile,
                $applicant,
                $permit->withVehicles(array_shift($ids), $ids),
                actor: $registrar,
            );

            if ($permit->submit) {
                $application = $this->submitPermit->handle($application, $registrar);
            }

            return [$applicant, $application];
        });

        return ['applicant' => $applicant, 'permit' => $application];
    }

    /**
     * A new request of an organization already on record (Permits › Add new,
     * "existing organisation"): filed for the same applicant, with the
     * organization's own vehicles where it has them already (by plate) and
     * any other vehicle added to its register — all of it or nothing.
     *
     * @param  list<VehicleData>  $vehicles  the primary vehicle first, then the trailers
     * @return array{applicant: User, permit: Permit}
     */
    public function forExisting(User $applicant, array $vehicles, WalkInPermitData $permit, User $registrar): array
    {
        $application = DB::transaction(function () use ($applicant, $vehicles, $permit, $registrar): Permit {
            /** @var Organization $profile */
            $profile = $applicant->organization;

            $ids = array_map(
                fn (VehicleData $vehicle): string => Vehicle::query()
                    ->whereBelongsTo($profile)
                    ->where('plate_normalized', $vehicle->plate->normalized)
                    ->value('id') ?? $this->registerVehicle->handle($profile, $vehicle)->id,
                $vehicles,
            );

            $application = $this->createDraftPermit->handle(
                $profile,
                $applicant,
                $permit->withVehicles(array_shift($ids), $ids),
                actor: $registrar,
            );

            return $permit->submit ? $this->submitPermit->handle($application, $registrar) : $application;
        });

        return ['applicant' => $applicant, 'permit' => $application];
    }
}
