<?php

use App\Actions\Permits\SubmitPermit;
use App\Actions\Vehicles\UpdateVehicle;
use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Operations and hardening (spec §15, §16): the health check, the first
 * Admin, rate limits on the account forms and the plate lock.
 */

describe('health check', function () {
    it('answers 200 when the database, cache and queue work', function () {
        $this->get('/up')->assertOk();
    });

    it('answers 500 and names only the service when one is down', function () {
        config(['queue.default' => 'missing-connection', 'app.debug' => false]);

        $response = $this->get('/up');

        $response->assertStatus(500);
        expect($response->getContent())->not->toContain('missing-connection');
    });
});

describe('admin:create', function () {
    it('opens the first Admin and prints the link to choose a password', function () {
        Notification::fake();

        $this->artisan('admin:create', ['email' => 'Chief@Example.test', '--name' => 'Chief Admin'])
            ->expectsOutputToContain('Admin account opened for chief@example.test.')
            ->expectsOutputToContain('/reset-password/')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'chief@example.test')->sole();

        expect($admin->hasRole('admin'))->toBeTrue()->and($admin->name)->toBe('Chief Admin');

        // The printed link is the one to use: no e-mail with a second, competing link.
        Notification::assertNothingSent();
    });

    it('refuses an address that is taken or invalid', function () {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->artisan('admin:create', ['email' => 'taken@example.test'])->assertFailed();
        $this->artisan('admin:create', ['email' => 'not-an-email'])->assertFailed();

        expect(User::role('admin')->count())->toBe(0);
    });
});

describe('rate limits on the account forms', function () {
    it('limits password-reset e-mails per IP', function () {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => "nobody{$attempt}@example.test"]);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertStatus(429);
    });
});

describe('plate lock', function () {
    it('locks the vehicle rows when an application is submitted and when a plate changes', function () {
        $applicant = applicantWithOrganization();
        $vehicle = Vehicle::factory()->create(['organization_id' => $applicant->organization_id]);
        $permit = Permit::factory()->forVehicles($vehicle)->draft()->create([
            'organization_id' => $applicant->organization_id,
            'applicant_id' => $applicant->id,
        ]);

        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_contains($query->sql, 'from `vehicles`') && str_contains($query->sql, 'for update')) {
                $locks[] = $query->sql;
            }
        });

        app(SubmitPermit::class)->handle($permit, $applicant);
        expect($locks)->toHaveCount(1);

        $other = Vehicle::factory()->create(['organization_id' => $applicant->organization_id]);
        app(UpdateVehicle::class)->handle($other, new VehicleData(PlateNumber::parse('ኢት-03-99998'), $other->vehicle_type_id, 'ሌላ ባለንብረት'));
        expect($locks)->toHaveCount(2);
    });
});
