<?php

namespace App\Actions\Users;

use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Vehicles\RegisterVehicle;
use App\Data\OrganizationData;
use App\Data\VehicleData;
use App\Data\WalkInPermitData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\RequestLetter;
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
        private readonly AuditLog $audit,
    ) {}

    /**
     * The office's one-page form (as the paper form): the applicant and their
     * organization, the vehicle and its trailers, and the permit asked for,
     * saved together by the same Actions as online — all of it or nothing.
     * No payment is taken here: the fee is paid once the permit is approved.
     * The applicant's account is a record only: nobody signs in with it.
     *
     * @param  list<VehicleData>  $vehicles  the primary vehicle first, then the trailers
     * @param  list<list<VehicleData>>  $more  the other vehicles the same letter asks for, each with its trailers
     * @return array{applicant: User, permit: Permit|null, letter: RequestLetter|null}
     */
    public function handle(
        string $name,
        string $email,
        string $locale,
        OrganizationData $organization,
        array $vehicles,
        ?WalkInPermitData $permit,
        User $registrar,
        array $more = [],
    ): array {
        [$applicant, $application, $letter] = DB::transaction(function () use ($name, $email, $locale, $organization, $vehicles, $permit, $registrar, $more): array {
            $applicant = $this->registerApplicant->handle($name, $email, $locale, $organization, $registrar);

            /** @var Organization $profile */
            $profile = $applicant->organization;

            if ($permit === null || $vehicles === []) {
                foreach ($vehicles as $vehicle) {
                    $this->registerVehicle->handle($profile, $vehicle);
                }

                return [$applicant, null, null];
            }

            return [$applicant, ...$this->requestFor($profile, $applicant, [$vehicles, ...$more], $permit, $registrar, fn (VehicleData $vehicle): string => $this->registerVehicle->handle($profile, $vehicle)->id)];
        });

        return ['applicant' => $applicant, 'permit' => $application, 'letter' => $letter];
    }

    /**
     * A new request of an organization already on record (Permits › Add new,
     * "existing organisation"): filed for the same applicant, with the
     * organization's own vehicles where it has them already (by plate) and
     * any other vehicle added to its register — all of it or nothing.
     *
     * @param  list<VehicleData>  $vehicles  the primary vehicle first, then the trailers
     * @param  list<list<VehicleData>>  $more  the other vehicles the same letter asks for, each with its trailers
     * @return array{applicant: User, permit: Permit, letter: RequestLetter|null}
     */
    public function forExisting(User $applicant, array $vehicles, WalkInPermitData $permit, User $registrar, array $more = []): array
    {
        [$application, $letter] = DB::transaction(function () use ($applicant, $vehicles, $permit, $registrar, $more): array {
            /** @var Organization $profile */
            $profile = $applicant->organization;

            return $this->requestFor($profile, $applicant, [$vehicles, ...$more], $permit, $registrar, fn (VehicleData $vehicle): string => Vehicle::query()
                ->whereBelongsTo($profile)
                ->where('plate_normalized', $vehicle->plate->normalized)
                ->value('id') ?? $this->registerVehicle->handle($profile, $vehicle)->id);
        });

        return ['applicant' => $applicant, 'permit' => $application, 'letter' => $letter];
    }

    /**
     * One permit per vehicle the letter asks for (each with its trailers),
     * all on the same terms, grouped under the letter. The letter's scan is
     * stored once and shared. Any vehicle that cannot be taken in stops the
     * whole letter: nothing is saved.
     *
     * @param  list<list<VehicleData>>  $units  each the primary vehicle first, then its trailers
     * @param  callable(VehicleData): string  $vehicleId  the id of a vehicle, registering it when new
     * @return array{0: Permit, 1: RequestLetter|null} the first permit and the letter
     */
    private function requestFor(Organization $profile, User $applicant, array $units, WalkInPermitData $permit, User $registrar, callable $vehicleId): array
    {
        $first = null;
        $letter = null;

        foreach ($units as $index => $unit) {
            $ids = array_map($vehicleId, $unit);
            $terms = $index === 0 ? $permit : $permit->forAnotherVehicle();

            $application = $this->createDraftPermit->handle($profile, $applicant, $terms->withVehicles(array_shift($ids), $ids), actor: $registrar);

            if ($first === null) {
                $first = $application;

                if ($permit->letter !== null) {
                    $letter = new RequestLetter([
                        'letter_number' => $application->letter_number,
                        'letter_date' => $application->letter_date,
                        'letter_file_path' => $application->letter_file_path,
                    ]);
                    $letter->organization_id = $profile->id;
                    $letter->applicant_id = $applicant->id;
                    $letter->registered_by = $registrar->id;
                    $letter->save();

                    $this->audit->record(AuditEvent::RequestLetterRegistered, $letter, $registrar, context: [
                        'letter_number' => $letter->letter_number,
                        'vehicles' => count($units),
                    ]);
                }
            } elseif ($first->letter_file_path !== null) {
                $application->letter_file_path = $first->letter_file_path;
            }

            if ($letter !== null) {
                $application->request_letter_id = $letter->id;
                $application->save();
            }

            $application = $permit->submit ? $this->submitPermit->handle($application, $registrar) : $application;

            if ($index === 0) {
                $first = $application;
            }
        }

        /** @var Permit $first */
        return [$first, $letter];
    }
}
