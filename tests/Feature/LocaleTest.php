<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses Amharic by default', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="am"', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'am')
            ->where('locales', ['am' => 'አማርኛ', 'en' => 'English'])
            ->where('translations.common.language', 'ቋንቋ'));
});

it('lets a guest switch language for their session', function () {
    $this->from(route('home'))
        ->put(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'en');

    $this->get(route('login'))
        ->assertSee('<html lang="en"', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('translations.common.language', 'Language'));
});

it('persists the choice on the account of a signed-in user', function () {
    $user = User::factory()->create();

    expect($user->refresh()->locale)->toBe('am');

    $this->actingAs($user)->put(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    expect($user->refresh()->locale)->toBe('en');
});

it('prefers the saved preference of the user over the session', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->withSession(['locale' => 'am'])
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('rejects a language that is not supported', function (mixed $locale) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('locale.update'), ['locale' => $locale])
        ->assertSessionHasErrors('locale')
        ->assertSessionMissing('locale');

    expect($user->refresh()->locale)->toBe('am');
})->with([
    'unsupported' => ['fr'],
    'empty' => [''],
    'missing' => [null],
    'array' => [['en']],
    'wrong case' => ['EN'],
    'path traversal' => ['../en'],
]);

it('ignores an unsupported locale that is already in the session', function () {
    $this->withSession(['locale' => 'fr'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'am'));
});

it('rate limits language switching', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->put(route('locale.update'), ['locale' => 'en'])->assertRedirect();
    }

    $this->put(route('locale.update'), ['locale' => 'en'])->assertTooManyRequests();
});

it('sends only the frontend translation namespaces to the browser', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('translations', fn ($translations) => collect($translations)->keys()->all() === config('localization.frontend_namespaces'))
            ->missing('translations.validation')
            ->missing('translations.passwords')
            ->missing('translations.auth'));
});

it('falls back to English for a line that has no Amharic translation', function () {
    config(['localization.frontend_namespaces' => ['pagination']]);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'am')
            ->where('translations.pagination.next', 'Next &raquo;'));
});

it('shares only whitelisted fields of the signed-in user', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('registrar');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', fn ($shared) => collect($shared)->keys()->sort()->values()->all()
                === ['email', 'email_verified_at', 'has_organization', 'id', 'locale', 'name', 'roles'])
            ->where('auth.user.roles', ['registrar'])
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.two_factor_recovery_codes'));
});

it('shares no user for guests', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('auth.mustEnableTwoFactor', false));
});

it('does not load fonts from a third-party host', function () {
    $this->get(route('login'))
        ->assertDontSee('fonts.bunny.net')
        ->assertDontSee('fonts.googleapis.com')
        ->assertDontSee('fonts.gstatic.com');

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain("@import '@fontsource-variable/noto-sans-ethiopic/wght.css'")
        ->toContain("'Noto Sans Ethiopic Variable'")
        ->not->toMatch('/@import\s+url\(|fonts\.(bunny|googleapis|gstatic)|https:\/\//');
});
