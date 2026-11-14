<?php

use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A message left by the previous request ("Application approved.") is shown
 * once. It travels as Inertia flash data, which the browser does not keep in
 * its history: as a page prop it was saved with the page, and going back to
 * that page showed the message again each time.
 */

test('a message from the previous request is sent as one-time flash data, not as a page prop', function () {
    $this->actingAs(staffUser(Role::Registrar))
        ->withSession(['success' => 'Saved.', 'warning' => 'Check the dates.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->hasFlash('success', 'Saved.')
            ->hasFlash('warning', 'Check the dates.')
            ->missing('flash'));
});

test('a page with no message carries no flash data', function () {
    $this->actingAs(staffUser(Role::Registrar))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missingFlash('success')
            ->missingFlash('warning')
            ->missing('flash'));
});
