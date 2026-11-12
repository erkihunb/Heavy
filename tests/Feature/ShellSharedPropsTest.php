<?php

use App\Enums\Permission;
use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * What the signed-in shell ported from the source system (resources/js/shell) reads from
 * every page: the person's permissions, for hiding menu items, and the
 * presentation settings under the keys the source kit uses.
 */

it('shares the signed-in person\'s permissions for the menu', function () {
    $this->actingAs(staffUser(Role::Cashier))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains(Permission::VerifyPayments->value)
                && ! collect($permissions)->contains(Permission::ViewUsers->value))
            ->where('auth.isSuperAdmin', false));
});

it('shares the brand colour of the chosen preset', function () {
    config(['permits.appearance.theme' => 'forest', 'permits.appearance.primary_color' => null]);

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings', fn ($settings) => $settings['appearance.primary_color'] === '#166534'
                && $settings['appearance.sidebar_color'] === '#ffffff'));
});

it('prefers a custom brand colour and ignores an invalid one', function () {
    config(['permits.appearance.primary_color' => '#AB12CD', 'permits.appearance.sidebar_color' => 'red']);

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings', fn ($settings) => $settings['appearance.primary_color'] === '#ab12cd'
                && $settings['appearance.sidebar_color'] === '#ffffff'));
});

it('maps the appearance switches to the shell\'s keys', function () {
    config([
        'permits.appearance.allow_user_mode' => false,
        'permits.appearance.sidebar_compact' => true,
        'permits.appearance.show_breadcrumbs' => false,
    ]);

    $this->actingAs(staffUser(Role::Approver))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings', fn ($settings) => $settings['appearance.allow_user_theme_switching'] === false
                && $settings['appearance.sidebar_compact_default'] === true
                && $settings['appearance.show_breadcrumbs'] === false));
});
