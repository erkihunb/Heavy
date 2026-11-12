<?php

namespace App\Actions\Users;

use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\TakeReceiptAtIntake;
use App\Actions\Vehicles\RegisterVehicle;
use App\Data\OrganizationData;
use App\Data\PaymentData;
use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterWalkInApplication
{
    public function __construct(
        private readonly RegisterApplicant $registerApplicant,
        private readonly RegisterVehicle $registerVehicle,
        private readonly CreateDraftPermit $createDraftPermit,
        private readonly SubmitPermit $submitPermit,
        private readonly TakeReceiptAtIntake $takeReceipt,
    ) {}

    /**
     * The office's one-page form (as the paper form): the applicant and their
     * organization, the vehicle and its trailers, and the permit asked for,
     * saved together by the same Actions as online — all of it or nothing.
     * The applicant is e-mailed the password link only once it is saved.
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
        ?PaymentData $payment = null,
    ): array {
        [$applicant, $application] = DB::transaction(function () use ($name, $email, $locale, $organization, $vehicles, $permit, $registrar, $payment): array {
            $applicant = $this->registerApplicant->handle($name, $email, $locale, $organization, $registrar, notify: false);

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

                // The receipt brought with the request waits for the decision.
                if ($payment !== null) {
                    $this->takeReceipt->handle($application, $registrar, $payment);
                }
            }

            return [$applicant, $application];
        });

        $this->registerApplicant->sendPasswordLink($applicant);

        return ['applicant' => $applicant, 'permit' => $application];
    }
}
