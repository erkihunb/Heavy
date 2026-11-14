<?php

use App\Support\FontPreload;
use Illuminate\Support\Facades\Vite;

/*
 * The pages are drawn by scripts, so the browser learns which fonts it needs
 * only once they have run, and the words are then painted a second time when
 * the fonts arrive. The page's head asks for the fonts of the first screen
 * right away, beside the scripts.
 */

beforeEach(function () {
    // The suite swaps Vite for a fake; use the real one with a manifest of its own.
    $this->withVite();
    $this->build = 'build-test-'.uniqid();
    mkdir(public_path($this->build));
    file_put_contents(public_path("{$this->build}/manifest.json"), json_encode([
        FontPreload::INTER => ['file' => 'assets/inter-latin.woff2', 'src' => FontPreload::INTER],
        FontPreload::ABYSSINICA => ['file' => 'assets/abyssinica.woff2', 'src' => FontPreload::ABYSSINICA],
    ]));
    Vite::useBuildDirectory($this->build);
});

afterEach(function () {
    @unlink(public_path("{$this->build}/manifest.json"));
    @rmdir(public_path($this->build));
    @unlink(public_path('hot-test'));
});

it('asks for the Latin face in English and for the Ethiopic face too in Amharic', function () {
    $inter = '<link rel="preload" as="font" type="font/woff2" href="'.asset("{$this->build}/assets/inter-latin.woff2").'" crossorigin>';
    $abyssinica = '<link rel="preload" as="font" type="font/woff2" href="'.asset("{$this->build}/assets/abyssinica.woff2").'" crossorigin>';

    expect(FontPreload::tags('en'))->toBe($inter)
        ->and(FontPreload::tags('am'))->toBe($abyssinica."\n".$inter);
});

it('asks for nothing while the dev server serves the fonts, or when a font is not in the build', function () {
    file_put_contents(public_path('hot-test'), 'http://127.0.0.1:5199');
    Vite::useHotFile(public_path('hot-test'));
    expect(FontPreload::tags('am'))->toBe('');

    @unlink(public_path('hot-test'));
    file_put_contents(public_path("{$this->build}/manifest.json"), json_encode([]));
    expect(FontPreload::tags('am'))->toBe('');
});
