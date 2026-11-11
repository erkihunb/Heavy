<?php

use App\Console\Commands\GenerateTranslationTypes;
use App\Support\FrontendTranslations;
use Illuminate\Support\Arr;

// Datasets are resolved before the application boots, so the locales are listed
// here and checked against the configuration by the first test.
dataset('locales', ['am', 'en']);

it('covers every supported locale', function () {
    expect(array_keys(config('localization.supported')))->toBe(['am', 'en']);
});

it('has a language file for every frontend namespace in every supported locale', function (string $locale) {
    foreach (FrontendTranslations::namespaces() as $namespace) {
        expect(is_file(lang_path("{$locale}/{$namespace}.php")))->toBeTrue("lang/{$locale}/{$namespace}.php is missing");
    }
})->with('locales');

it('has exactly the same frontend keys in Amharic and English', function () {
    expect(FrontendTranslations::keys('am'))->toBe(FrontendTranslations::keys('en'));
});

it('has no empty frontend translation', function (string $locale) {
    foreach (Arr::dot(FrontendTranslations::for($locale)) as $key => $line) {
        expect($line)->toBeString("{$locale}.{$key}")->not->toBe('', "{$locale}.{$key}");
    }
})->with('locales');

it('has the same server-side authentication messages in Amharic and English', function (string $file) {
    $keys = fn (string $locale) => collect(Arr::dot(require lang_path("{$locale}/{$file}.php")))->keys()->sort()->values()->all();

    expect($keys('am'))->toBe($keys('en'));
})->with(['auth', 'passwords']);

it('keeps the generated TypeScript key type in sync with the language files', function () {
    $this->artisan('translations:types --check')->assertSuccessful();
});

it('generates a union of every frontend key', function () {
    $typescript = GenerateTranslationTypes::render();

    expect($typescript)->toContain('export type TranslationKey =');

    foreach (FrontendTranslations::keys() as $key) {
        expect($typescript)->toContain("| '{$key}'");
    }
});

it('fails the check when the generated file is stale', function () {
    config(['localization.frontend_namespaces' => ['common']]);

    $this->artisan('translations:types --check')->assertFailed();
});
