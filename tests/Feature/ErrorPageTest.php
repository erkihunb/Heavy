<?php

use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Error pages are drawn in the system's own design, with the status kept;
 * JSON callers keep Laravel's plain answer.
 */

it('shows the error page for an address that does not exist', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
            ->has('translations.errors.messages'));
});

it('shows the error page when a person may not open a page', function () {
    $this->actingAs(staffUser(Role::Approver))
        ->get(route('admin.audit-log.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
});

it('keeps a JSON answer for JSON callers', function () {
    $this->getJson('/no-such-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('hides server errors behind the error page outside debug mode', function () {
    config(['app.debug' => false]);

    Route::get('/boom', fn () => throw new RuntimeException('secret detail'))->middleware('web');

    $this->get('/boom')
        ->assertStatus(500)
        ->assertDontSee('secret detail')
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500));
});
