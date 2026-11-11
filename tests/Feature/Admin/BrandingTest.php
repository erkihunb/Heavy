<?php

use App\Domain\Audit\AuditEvent;
use App\Domain\Settings\Branding;
use App\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Branding (System settings → General, as in EUISIS): an Admin uploads the
 * logo and browser-tab icon, and sets the support contacts and the sign-in
 * message; everyone sees them.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->admin = staffUser(Role::Admin);
});

/**
 * Save the General section with files: multipart travels as POST + _method.
 *
 * @param  array<string, mixed>  $changes
 */
function saveGeneral(array $changes): TestResponse
{
    return test()->actingAs(test()->admin)
        ->post(route('admin.settings.update', 'general'), [...settingsForm('general'), ...$changes, '_method' => 'put']);
}

it('uploads a logo, re-encodes it, serves it and shares it with every page', function () {
    saveGeneral(['permits__branding__logo' => UploadedFile::fake()->image('Our Logo.jpg', 200, 200)])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.show', 'general'));

    $path = Branding::path('logo');

    // A generated name, written again as WebP (a photo), never the uploader's name.
    expect($path)->toStartWith('branding/logo-')->toEndWith('.webp')->not->toContain('Our Logo');
    Storage::disk('local')->assertExists($path);

    $this->get(Branding::url('logo'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('branding.logo', Branding::url('logo')));

    $entry = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->latest('id')->first();
    expect($entry->properties['attributes'])->toBe(['permits.branding.logo' => 'file'])
        ->and($entry->properties['old'])->toBe(['permits.branding.logo' => null]);
});

it('replaces the old file and takes an image down on request', function () {
    saveGeneral(['permits__branding__favicon' => UploadedFile::fake()->image('icon.png', 64, 64)]);
    $first = Branding::path('favicon');

    saveGeneral(['permits__branding__favicon' => UploadedFile::fake()->image('icon.png', 64, 64)]);
    $second = Branding::path('favicon');

    expect($second)->not->toBe($first)->toEndWith('.png');
    Storage::disk('local')->assertMissing($first);

    $this->get(route('home'))->assertSee('href="'.e(Branding::url('favicon')).'"', false);

    saveGeneral(['remove_assets' => ['permits__branding__favicon']])->assertSessionHasNoErrors();

    expect(Branding::path('favicon'))->toBeNull();
    Storage::disk('local')->assertMissing($second);
    $this->get(route('branding', 'favicon'))->assertNotFound();
    $this->get(route('home'))->assertSee('href="/favicon.svg"', false);
});

it('refuses files that are not a picture it accepts', function (string $field, UploadedFile $file) {
    saveGeneral([$field => $file])->assertSessionHasErrors($field);

    expect(DB::table('settings')->where('key', 'like', 'permits.branding.%')->exists())->toBeFalse();
})->with([
    'an SVG logo' => fn () => ['permits__branding__logo', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'a PDF logo' => fn () => ['permits__branding__logo', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
    'a tiny logo' => fn () => ['permits__branding__logo', UploadedFile::fake()->image('logo.png', 8, 8)],
    'a JPEG icon' => fn () => ['permits__branding__favicon', UploadedFile::fake()->image('icon.jpg', 64, 64)],
]);

it('lets only admins change the branding, while anyone may load it', function () {
    $this->actingAs(staffUser(Role::Approver))
        ->post(route('admin.settings.update', 'general'), ['_method' => 'put', 'permits__branding__logo' => UploadedFile::fake()->image('logo.png', 64, 64)])
        ->assertForbidden();

    expect(Branding::path('logo'))->toBeNull();
    $this->get(route('branding', 'logo'))->assertNotFound();
});

it('shows the support contacts and the sign-in message in the person\'s language', function () {
    saveGeneral([
        'permits__branding__support_email' => 'help@example.test',
        'permits__branding__support_phone' => '+251 11 123 4567',
        'permits__branding__login_message__en' => 'Open Monday to Friday.',
        'permits__branding__login_message__am' => 'ከሰኞ እስከ ዓርብ ክፍት ነው።',
    ])->assertSessionHasNoErrors();

    auth()->logout();
    $this->withCookie('locale', 'en');

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('branding.supportEmail', 'help@example.test')
        ->where('branding.supportPhone', '+251 11 123 4567')
        ->where('branding.loginMessage', fn (string $message) => in_array($message, ['Open Monday to Friday.', 'ከሰኞ እስከ ዓርብ ክፍት ነው።'], true)));

    $this->get(route('verification.form'))->assertSee('help@example.test');
});

it('validates the support phone', function () {
    saveGeneral(['permits__branding__support_phone' => 'call us'])->assertSessionHasErrors('permits__branding__support_phone');
});

it('puts the uploaded logo on the permit document unless one is configured', function () {
    expect(Branding::documentLogoPath())->toBeNull();

    saveGeneral(['permits__branding__logo' => UploadedFile::fake()->image('logo.png', 64, 64)]);

    expect(Branding::documentLogoPath())->toBe(Storage::disk('local')->path(Branding::path('logo')));

    $file = tempnam(sys_get_temp_dir(), 'logo');
    config(['permits.document.logo_path' => $file]);

    expect(Branding::documentLogoPath())->toBe($file);
    @unlink($file);
});

it('hides the language switch when the admin turns it off', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'appearance'), settingsForm('appearance', ['permits.appearance.show_language_switcher' => false]))
        ->assertSessionHasNoErrors();

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('appearance.showLanguageSwitcher', false));
});
