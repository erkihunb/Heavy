<?php

use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\WithdrawPermit;
use App\Actions\Vehicles\UpdateVehicle;
use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitStatus;
use App\Exceptions\VehiclePlateLocked;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;

/*
 * Once a vehicle is on a submitted application its plate is part of that
 * record: correcting it would rewrite the history of the permit.
 */

beforeEach(function () {
    $this->user = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->user->organization)->withPlate('ኢት-03-40134')->create([
        'owner_name' => 'አበበ ከበደ',
    ]);
});

describe('when the plate is locked', function () {
    it('is not locked for a vehicle that is on no application', function () {
        expect($this->vehicle->hasLockedPlate())->toBeFalse();
    });

    it('is not locked by a draft or a withdrawn application', function (PermitStatus $status) {
        Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect($this->vehicle->hasLockedPlate())->toBeFalse();
    })->with([PermitStatus::Draft, PermitStatus::Withdrawn]);

    it('is locked from submission on, whatever happens to the application afterwards', function (PermitStatus $status) {
        Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        expect($this->vehicle->hasLockedPlate())->toBeTrue();
    })->with(array_values(array_filter(
        PermitStatus::stored(),
        fn (PermitStatus $status): bool => ! in_array($status, [PermitStatus::Draft, PermitStatus::Withdrawn], true),
    )));

    it('is locked for a trailer as well', function () {
        $tractor = Vehicle::factory()->for($this->user->organization)->create();
        Permit::factory()->forVehicles($tractor, $this->vehicle)->submitted()->create();

        expect($this->vehicle->hasLockedPlate())->toBeTrue();
    });

    it('is locked by one submitted application among drafts', function () {
        Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create();

        expect($this->vehicle->hasLockedPlate())->toBeFalse();

        Permit::factory()->forVehicles($this->vehicle)->rejected()->create();

        expect($this->vehicle->hasLockedPlate())->toBeTrue();
    });

    it('is not locked by the applications of other vehicles', function () {
        Permit::factory()->forVehicles(Vehicle::factory()->for($this->user->organization)->create())->issued()->create();

        expect($this->vehicle->hasLockedPlate())->toBeFalse();
    });

    it('locks at submission and unlocks again when the application is withdrawn', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

        app(SubmitPermit::class)->handle($permit, $this->user);

        expect($this->vehicle->hasLockedPlate())->toBeTrue();

        app(WithdrawPermit::class)->handle($permit, $this->user);

        expect($this->vehicle->hasLockedPlate())->toBeFalse();
    });
});

describe('UpdateVehicle', function () {
    it('refuses to change a locked plate', function () {
        Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $data = new VehicleData(PlateNumber::parse('ኢት-03-99999'), $this->vehicle->vehicle_type_id, 'ሌላ ባለንብረት');

        try {
            app(UpdateVehicle::class)->handle($this->vehicle, $data);
            $this->fail('The locked plate was changed.');
        } catch (VehiclePlateLocked $exception) {
            expect($exception->plate)->toBe('ኢት-03-40134')
                ->and($exception->userMessage())->toBe(__('vehicles.errors.plate_locked'));
        }

        $this->vehicle->refresh();

        // Nothing was saved, not even the fields that could have been.
        expect($this->vehicle->plate_normalized)->toBe('ኢት-03-40134')
            ->and($this->vehicle->plate_raw)->toBe('ኢት-03-40134')
            ->and($this->vehicle->owner_name)->toBe('አበበ ከበደ');
    });

    it('still corrects the other details of a vehicle with a locked plate', function () {
        Permit::factory()->forVehicles($this->vehicle)->issued()->create();
        $type = VehicleType::factory()->create();

        app(UpdateVehicle::class)->handle(
            $this->vehicle,
            new VehicleData(PlateNumber::parse('ኢት-03-40134'), $type->id, 'ሌላ ባለንብረት'),
        );

        $this->vehicle->refresh();

        expect($this->vehicle->owner_name)->toBe('ሌላ ባለንብረት')
            ->and($this->vehicle->vehicle_type_id)->toBe($type->id)
            ->and($this->vehicle->plate_normalized)->toBe('ኢት-03-40134');
    });

    it('does not mistake the same plate typed differently for a change', function () {
        Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        app(UpdateVehicle::class)->handle(
            $this->vehicle,
            new VehicleData(PlateNumber::parse('et 3 40134'), $this->vehicle->vehicle_type_id, 'ሌላ ባለንብረት'),
        );

        expect($this->vehicle->refresh()->plate_normalized)->toBe('ኢት-03-40134')
            ->and($this->vehicle->owner_name)->toBe('ሌላ ባለንብረት');
    });

    it('changes the plate of a vehicle that is only on drafts and withdrawn applications', function () {
        Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create();

        app(UpdateVehicle::class)->handle(
            $this->vehicle,
            new VehicleData(PlateNumber::parse('ኢት-03-99999'), $this->vehicle->vehicle_type_id, 'አበበ ከበደ'),
        );

        expect($this->vehicle->refresh()->plate_normalized)->toBe('ኢት-03-99999');
    });
});
