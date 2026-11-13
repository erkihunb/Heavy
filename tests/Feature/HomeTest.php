<?php

use App\Models\User;

it('sends guests from the site root straight to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('sends signed-in people from the site root to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
