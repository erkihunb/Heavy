<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against the dedicated PostgreSQL
| test database (see phpunit.xml). Roles and permissions are reference data
| every deployment has, so they are seeded before each feature test.
|
| Unit tests are pure PHP: they do not boot the framework or touch the database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Feature');
