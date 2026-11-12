<?php

use App\Actions\Permits\SubmitPermit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Documents\PermitDocument;
use App\Domain\Permits\PermitNumberGenerator;
use App\Domain\Permits\VerificationTokenService;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Settings\SystemSettings;
use App\Domain\Settings\ThemeStyle;
use App\Domain\Sms\HttpSmsGateway;
use App\Domain\Sms\SmsGateway;
use App\Enums\Role;
use App\Jobs\SendTelegramMessage;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * System settings (spec §11): an overview, and sections whose values override
 * the configuration at once, are audited, and keep secrets encrypted.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function () {
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.settings.overview'))->assertForbidden();
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.settings.show', 'general'))->assertForbidden();
    $this->actingAs(staffUser(Role::Approver))->put(route('admin.settings.update', 'general'), [])->assertForbidden();
});

it('shows an overview of the system with what needs attention', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/overview')
            ->where('system.name', config('app.name'))
            ->where('services.database', 'mysql')
            ->where('services.two_factor', true)
            ->where('counts.staff', 1)
            ->where('warnings', fn ($warnings) => collect($warnings)->contains('placeholder_time_windows'))
            ->where('sections', SettingsRegistry::SECTIONS));
});

it('shows every section with its fields and never a secret', function (string $section) {
    if ($section === 'email') {
        config(['mail.mailers.smtp.password' => 'hunter2']);
    }

    $response = $this->actingAs($this->admin)
        ->get(route('admin.settings.show', $section))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/section')
            ->where('section', $section)
            ->has('fields', count(SettingsRegistry::section($section))));

    expect($response->getContent())->not->toContain('hunter2');
})->with(SettingsRegistry::SECTIONS);

it('does not know other sections', function () {
    $this->actingAs($this->admin)->get('/admin/settings/database')->assertNotFound();
});

it('saves a change, applies it at once and records before and after', function () {
    expect(config('permits.max_duration_days'))->toBe(90);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'permits'), settingsForm('permits', [
            'permits.max_duration_days' => 60,
            'permits.separation_of_duties' => false,
        ]))
        ->assertRedirect(route('admin.settings.show', 'permits'))
        ->assertSessionHas('success', __('admin.settings.messages.saved'));

    expect(config('permits.max_duration_days'))->toBe(60)
        ->and(config('permits.separation_of_duties'))->toBeFalse()
        ->and(DB::table('settings')->pluck('key')->sort()->values()->all())->toBe(['permits.max_duration_days', 'permits.separation_of_duties']);

    $entry = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->sole();
    expect($entry->properties['old'])->toBe(['permits.max_duration_days' => 90, 'permits.separation_of_duties' => true])
        ->and($entry->properties['attributes'])->toBe(['permits.max_duration_days' => 60, 'permits.separation_of_duties' => false])
        ->and($entry->properties['context'])->toBe(['section' => 'permits']);
});

it('lets an admin change the certificate code rule', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'codes'), settingsForm('codes', [
            'permits.certificate_code.enabled' => true,
            'permits.certificate_code.prefix' => 'AACRA',
            'permits.certificate_code.length' => 12,
        ]))
        ->assertSessionHasNoErrors();

    expect(config('permits.certificate_code.prefix'))->toBe('AACRA')
        ->and(config('permits.certificate_code.length'))->toBe(12);
});

it('lets an admin change the permit number rule for later approvals', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'codes'), settingsForm('codes', [
            'permits.number_prefix' => 'AA-HV',
            'permits.number_format' => '{year}-{prefix}-{seq}',
            'permits.number_digits' => 4,
        ]))
        ->assertSessionHasNoErrors();

    $at = EthiopianCalendar::toGregorian(2019, 5, 1)->setTime(9, 0);

    expect(app(PermitNumberGenerator::class)->next($at))->toBe('2019-AA-HV-0001');
});

it('gives the code rules page what its live preview needs', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.show', 'codes'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/section')
            ->where('preview.year', PermitNumberGenerator::year(CarbonImmutable::now()))
            ->where('preview.last.yearly', null)
            ->where('preview.last.never', null)
            ->has('categories'));
});

it('shows the e-mail settings in use now and where each comes from', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'email'), settingsForm('email', ['mail.from.name' => 'Roads Authority']))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.show', 'email'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inUse', fn ($rows): bool => collect($rows)->contains(fn (array $row): bool => $row['field'] === 'mail__from__name' && $row['value'] === 'Roads Authority' && $row['source'] === 'settings')
                && collect($rows)->contains(fn (array $row): bool => $row['field'] === 'mail__mailers__smtp__password' && $row['value'] === null)));
});

it('puts every section in exactly one menu category', function () {
    $listed = array_merge(...array_values(SettingsRegistry::CATEGORIES));

    expect($listed)->toBe(SettingsRegistry::SECTIONS)
        ->and(array_unique($listed))->toBe($listed);
});

it('lets an admin set the notes and the verification address printed on permits', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'documents'), settingsForm('documents', [
            'permits.document.notes.am' => 'የመጀመሪያው ማሳሰቢያ።

ሁለተኛው ማሳሰቢያ።',
            'permits.document.notes.en' => 'First note.
Second note.',
            'permits.verify_url' => 'https://permits.example.test',
        ]))
        ->assertSessionHasNoErrors();

    $permit = Permit::factory()->forVehicles(Vehicle::factory()->create())->active()->create();

    expect(PermitDocument::html($permit, 'token', 'am'))->toContain('<li>የመጀመሪያው ማሳሰቢያ።</li>')
        ->toContain('<li>ሁለተኛው ማሳሰቢያ።</li>')
        ->toContain('https://permits.example.test/verify')
        ->and(PermitDocument::html($permit, 'token', 'en'))->toContain('<li>Second note.</li>')
        ->and(VerificationTokenService::url('abc'))->toBe('https://permits.example.test/v/abc');
});

it('says so when nothing changed, and writes nothing', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'general'), settingsForm('general'))
        ->assertSessionHas('success', __('admin.settings.messages.unchanged'));

    expect(DB::table('settings')->count())->toBe(0)
        ->and(Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->count())->toBe(0);
});

it('applies saved values on the next boot and in queued jobs', function () {
    DB::table('settings')->insert(['key' => 'permits.max_trailers', 'value' => json_encode(4), 'updated_at' => now()]);
    Cache::forget(SystemSettings::CACHE_KEY);

    app(SystemSettings::class)->apply();
    expect(config('permits.max_trailers'))->toBe(4);

    // A worker that booted earlier picks up a change before its next job.
    config(['permits.max_trailers' => 2]);
    Event::dispatch(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));
    expect(config('permits.max_trailers'))->toBe(4);
});

it('validates each value', function (string $section, array $changes, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', $section), settingsForm($section, $changes))
        ->assertSessionHasErrors($field);
})->with([
    'not a verification address' => ['documents', ['permits.verify_url' => 'not a url'], 'permits__verify_url'],
    'no notes' => ['documents', ['permits.document.notes.en' => ''], 'permits__document__notes__en'],
    'too long a validity' => ['permits', ['permits.max_duration_days' => 400], 'permits__max_duration_days'],
    'lower-case number prefix' => ['codes', ['permits.number_prefix' => 'aacra'], 'permits__number_prefix'],
    'number rule without the counter' => ['codes', ['permits.number_format' => '{prefix}-{year}'], 'permits__number_format'],
    'number rule without the year' => ['codes', ['permits.number_format' => '{prefix}-{seq}'], 'permits__number_format'],
    'number rule with a slash' => ['codes', ['permits.number_format' => '{prefix}/{year}/{seq}'], 'permits__number_format'],
    'too few counter digits' => ['codes', ['permits.number_digits' => 2], 'permits__number_digits'],
    'lower-case type code' => ['codes', ['permits.number_type_codes.pass' => 'ps'], 'permits__number_type_codes__pass'],
    'unknown reset rule' => ['codes', ['permits.number_reset' => 'monthly'], 'permits__number_reset'],
    'counter starting at zero' => ['codes', ['permits.number_start' => 0], 'permits__number_start'],
    'lower-case certificate prefix' => ['codes', ['permits.certificate_code.prefix' => 'crt'], 'permits__certificate_code__prefix'],
    'certificate prefix with a hyphen' => ['codes', ['permits.certificate_code.prefix' => 'C-RT'], 'permits__certificate_code__prefix'],
    'too short a certificate code' => ['codes', ['permits.certificate_code.length' => 4], 'permits__certificate_code__length'],
    'too long a certificate code' => ['codes', ['permits.certificate_code.length' => 20], 'permits__certificate_code__length'],
    'empty restricted-hours text' => ['permits', ['permits.time_windows.pass.en' => ''], 'permits__time_windows__pass__en'],
    'unknown theme' => ['appearance', ['permits.appearance.theme' => 'pink'], 'permits__appearance__theme'],
    'bad reminder time' => ['notifications', ['permits.notifications.reminder_time' => '25:00'], 'permits__notifications__reminder_time'],
    'bad sender address' => ['email', ['mail.from.address' => 'nobody'], 'mail__from__address'],
    'SMTP without a host' => ['email', ['mail.default' => 'smtp', 'mail.mailers.smtp.host' => ''], 'mail__mailers__smtp__host'],
    'HTTP SMS without an endpoint' => ['sms', ['services.sms.driver' => 'http', 'services.sms.http.url' => ''], 'services__sms__http__url'],
    'Telegram on without a token' => ['telegram', ['services.telegram.enabled' => true, 'services.telegram.chat_id' => '-100123'], 'services__telegram__bot_token'],
    'bad chat id' => ['telegram', ['services.telegram.chat_id' => 'not a chat'], 'services__telegram__chat_id'],
    'unknown language' => ['localization', ['app.locale' => 'fr'], 'app__locale'],
    'unknown timezone' => ['localization', ['localization.display_timezone' => 'Mars/Olympus'], 'localization__display_timezone'],
    'too short a password' => ['security', ['permits.security.password_min_length' => 4], 'permits__security__password_min_length'],
]);

it('keeps secrets encrypted, never shows them, and audits only that they changed', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'email'), settingsForm('email', [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.password' => 's3cret-pass',
        ]))
        ->assertSessionHasNoErrors();

    $stored = json_decode((string) DB::table('settings')->where('key', 'mail.mailers.smtp.password')->value('value'), true);

    expect($stored)->not->toBe('s3cret-pass')
        ->and(Crypt::decryptString($stored))->toBe('s3cret-pass')
        ->and(config('mail.mailers.smtp.password'))->toBe('s3cret-pass')
        ->and(config('mail.default'))->toBe('smtp');

    $entry = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->sole();
    expect(json_encode($entry->properties))->not->toContain('s3cret-pass')
        ->and($entry->properties['attributes']['mail.mailers.smtp.password'])->toBe(SystemSettings::MASK);

    // Left empty, the secret is kept; ticking "remove" empties it.
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'email'), settingsForm('email'));
    expect(config('mail.mailers.smtp.password'))->toBe('s3cret-pass');

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'email'), settingsForm('email', ['mail__mailers__smtp__password__clear' => true]));
    expect(config('mail.mailers.smtp.password'))->toBeNull();
});

it('ignores a secret saved under another application key', function () {
    DB::table('settings')->insert(['key' => 'services.sms.http.token', 'value' => json_encode('not-encrypted-with-this-key'), 'updated_at' => now()]);
    Cache::forget(SystemSettings::CACHE_KEY);
    config(['services.sms.http.token' => 'from-env']);

    app(SystemSettings::class)->apply();

    expect(config('services.sms.http.token'))->toBe('from-env');
});

it('sends a test e-mail to the admin', function () {
    Mail::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.test', 'email'))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('reports a test that fails instead of failing', function () {
    config(['services.sms.driver' => 'http', 'services.sms.http.url' => 'https://sms.example.test/send']);
    Http::fake(['sms.example.test/*' => Http::response('nope', 500)]);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.test', 'sms'), ['phone' => '0911223344'])
        ->assertRedirect()
        ->assertSessionHas('warning');

    $this->actingAs($this->admin)
        ->post(route('admin.settings.test', 'sms'), ['phone' => 'abc'])
        ->assertSessionHasErrors('phone');
});

it('posts SMS to an HTTP provider with a bearer token', function () {
    config(['services.sms.driver' => 'http', 'services.sms.http.url' => 'https://sms.example.test/send', 'services.sms.http.token' => 'tok', 'services.sms.http.sender' => 'TMA']);
    Http::fake(['sms.example.test/*' => Http::response(['ok' => true])]);

    expect(app(SmsGateway::class))->toBeInstanceOf(HttpSmsGateway::class);

    app(SmsGateway::class)->send('+251911223344', 'Hello');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://sms.example.test/send'
        && $request->hasHeader('Authorization', 'Bearer tok')
        && $request['to'] === '+251911223344'
        && $request['message'] === 'Hello'
        && $request['sender'] === 'TMA');
});

it('sends a Telegram test through the Bot API', function () {
    config(['services.telegram.bot_token' => '123:abc', 'services.telegram.chat_id' => '-100123']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.test', 'telegram'))
        ->assertSessionHas('success');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bot123:abc/sendMessage'
        && $request['chat_id'] === '-100123');
});

it('alerts the staff chat when an application is submitted, with no personal data', function () {
    Queue::fake();
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123:abc', 'services.telegram.chat_id' => '-100123']);

    $applicant = applicantWithOrganization();
    $permit = Permit::factory()->forVehicles(Vehicle::factory()->create(['organization_id' => $applicant->organization_id]))
        ->draft()->create(['organization_id' => $applicant->organization_id, 'applicant_id' => $applicant->id]);

    app(SubmitPermit::class)->handle($permit, $applicant);

    Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job) use ($permit, $applicant): bool {
        return str_contains($job->text, route('staff.permits.show', $permit))
            && ! str_contains($job->text, $applicant->email)
            && ! str_contains($job->text, (string) $permit->organization->name_am);
    });
});

it('sends no alert when Telegram is off or the alert is switched off', function () {
    Queue::fake();
    $applicant = applicantWithOrganization();
    $permit = fn () => Permit::factory()->forVehicles(Vehicle::factory()->create(['organization_id' => $applicant->organization_id]))
        ->draft()->create(['organization_id' => $applicant->organization_id, 'applicant_id' => $applicant->id]);

    app(SubmitPermit::class)->handle($permit(), $applicant);

    config(['services.telegram.enabled' => true, 'services.telegram.alerts.submitted' => false]);
    app(SubmitPermit::class)->handle($permit(), $applicant);

    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('applies the theme and the default mode to every page', function () {
    config(['permits.appearance.theme' => 'forest', 'permits.appearance.default_mode' => 'dark']);

    $this->get(route('home'))
        ->assertSee('data-theme="forest"', false)
        ->assertSee('data-default-appearance="dark"', false)
        ->assertSee('class="dark"', false);

    // A person's own choice wins.
    $this->withUnencryptedCookie('appearance_mode', 'light')
        ->get(route('home'))
        ->assertDontSee('class="dark"', false);
});

it('uses the default language for people who have not chosen one', function () {
    config(['app.locale' => 'en']);

    $this->get(route('home'))->assertSee('<html lang="en"', false);
});

describe('theme management', function () {
    it('puts the interface style, shapes and density on every page', function () {
        config(['permits.appearance.ui_style' => 'soft', 'permits.appearance.button_style' => 'pill', 'permits.appearance.card_radius' => 'lg', 'permits.appearance.table_density' => 'compact', 'permits.appearance.animations' => false]);

        $this->get(route('home'))
            ->assertSee('data-ui-style="soft"', false)
            ->assertSee('data-button-style="pill"', false)
            ->assertSee('data-card-radius="lg"', false)
            ->assertSee('data-table-density="compact"', false)
            ->assertSee('data-animations="off"', false);
    });

    it('turns custom colours into light and dark variables with readable text', function () {
        config(['permits.appearance.primary_color' => '#0B5D1E', 'permits.appearance.sidebar_color' => '#FFFFFF', 'permits.appearance.accent_color' => '#f5c400']);

        $css = ThemeStyle::css();

        expect($css)->toContain(':root:root{--primary:#0b5d1e;')
            ->toContain('--primary-foreground:#ffffff;')
            ->toContain('--brand-accent:#f5c400;')
            ->toContain('--sidebar:#ffffff;--sidebar-foreground:#1f2937;')
            ->toContain(':root:root.dark{')
            // A white sidebar is not carried into dark mode.
            ->and(str($css)->after('.dark{')->toString())->not->toContain('--sidebar:#ffffff');

        expect(ThemeStyle::readable('#fde68a'))->toBe('#141414');

        $this->get(route('home'))->assertSee('--primary:#0b5d1e', false);
    });

    it('ignores colours that are not hex and sends no style without them', function () {
        config(['permits.appearance.primary_color' => 'red;}body{display:none']);

        expect(ThemeStyle::css())->toBe('');
    });

    it('validates colours on save', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'appearance'), settingsForm('appearance', ['permits.appearance.primary_color' => 'blue']))
            ->assertSessionHasErrors('permits__appearance__primary_color');

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'appearance'), settingsForm('appearance', ['permits.appearance.primary_color' => '#123456']))
            ->assertSessionHasNoErrors();

        expect(config('permits.appearance.primary_color'))->toBe('#123456');
    });

    it('applies the default mode to everyone when people may not choose', function () {
        config(['permits.appearance.allow_user_mode' => false, 'permits.appearance.default_mode' => 'dark']);

        $this->withUnencryptedCookie('appearance_mode', 'light')
            ->get(route('home'))
            ->assertSee('class="dark"', false)
            ->assertSee('data-user-mode="off"', false);
    });

    it('starts with the sidebar collapsed only for people who never chose', function () {
        config(['permits.appearance.sidebar_compact' => true]);
        $user = staffUser(Role::Approver);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('sidebarOpen', false)->where('appearance.showBreadcrumbs', true));

        $this->actingAs($user)->withUnencryptedCookie('sidebar_state', 'true')->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('sidebarOpen', true));
    });
});
