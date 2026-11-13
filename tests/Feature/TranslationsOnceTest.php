<?php

use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\FrontendTranslations;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;

/*
 * Every word of the interface (about 125 KB) goes out with the first page
 * only; the browser keeps it (an Inertia once prop) and later visits leave it
 * out, until the language or the wording changes.
 */

beforeEach(function () {
    $this->user = staffUser(Role::Registrar);
});

function translationsKey(string $locale): string
{
    return "translations.{$locale}.".FrontendTranslations::version($locale);
}

/**
 * A visit made by Inertia in the browser, saying which once props it holds.
 *
 * @param  list<string>  $held
 */
function inertiaVisit(string $url, array $held = []): TestResponse
{
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];

    if ($held !== []) {
        $headers['X-Inertia-Except-Once-Props'] = implode(',', $held);
    }

    return test()->get($url, $headers);
}

it('sends the words with the first page, under a key naming the language and the wording', function () {
    $page = $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()->viewData('page');

    expect($page['props']['translations']['common'])->toBeArray()
        ->and($page['onceProps'])->toHaveKey(translationsKey('am'))
        ->and($page['onceProps'][translationsKey('am')]['prop'])->toBe('translations');
});

it('leaves them out of later visits in the same language', function () {
    $this->actingAs($this->user);

    $visit = inertiaVisit(route('profile.edit'), [translationsKey('am')])->assertOk();

    expect($visit->json('props'))->not->toHaveKey('translations')
        // Still named, so the browser keeps the words it has.
        ->and($visit->json('onceProps'))->toHaveKey(translationsKey('am'));
});

it('sends them again in the new language after a switch', function () {
    $this->actingAs($this->user)->put(route('locale.update'), ['locale' => 'en'])->assertRedirect();

    $visit = inertiaVisit(route('profile.edit'), [translationsKey('am')])->assertOk();

    expect($visit->json('props.translations.common'))->toBe(FrontendTranslations::for('en')['common'])
        ->and($visit->json('onceProps'))->toHaveKey(translationsKey('en'));
});

it('sends them again to a browser that has held the words of two languages', function () {
    // Switched back and forth: it says it has both, and cannot tell which it shows.
    $this->actingAs($this->user);

    $visit = inertiaVisit(route('profile.edit'), [translationsKey('am'), translationsKey('en')])->assertOk();

    expect($visit->json('props.translations.common'))->toBe(FrontendTranslations::for('am')['common']);
});

it('changes the key when the wording changes', function () {
    $copy = sys_get_temp_dir().'/heavy-lang-'.uniqid();
    File::copyDirectory(lang_path(), $copy);
    app()->useLangPath($copy);

    try {
        $before = FrontendTranslations::version('am');
        expect(FrontendTranslations::version('am'))->toBe($before);

        file_put_contents("{$copy}/am/common.php", "\n// changed\n", FILE_APPEND);
        clearstatcache();

        expect(FrontendTranslations::version('am'))->not->toBe($before);
    } finally {
        File::deleteDirectory($copy);
    }
});
