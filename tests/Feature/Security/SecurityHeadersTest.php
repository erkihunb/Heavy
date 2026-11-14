<?php

use Illuminate\Support\Facades\Vite;

/*
 * Security headers on every response (spec §15).
 */

it('sends the security headers on pages', function () {
    $response = $this->get(route('login'))->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=(self)')->toContain('microphone=()')
        ->and($response->headers->get('Cross-Origin-Opener-Policy'))->toBe('same-origin');
});

it('allows scripts only from this site or with the nonce of the request', function () {
    $response = $this->get(route('login'))->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->not->toContain("'unsafe-eval'")
        ->and(preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $match))->toBe(1);

    // The inline appearance script carries the same nonce.
    $response->assertSee('<script nonce="'.$match[1].'">', false);

    // Every request gets a new nonce.
    $again = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
    expect($again)->not->toContain($match[1]);
});

it('sends the headers on JSON, redirects and errors too', function () {
    $this->get(route('dashboard'))->assertRedirect()->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/does-not-exist')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/up')->assertOk()->assertHeader('X-Frame-Options', 'DENY');
});

it('keeps the stricter referrer policy of the verification pages', function () {
    $this->get(route('verification.form'))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('sends HSTS only over HTTPS in production', function () {
    $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');

    $this->app->detectEnvironment(fn (): string => 'production');

    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('allows the Vite dev server only locally while it runs', function () {
    // The suite swaps Vite for a fake that ignores useHotFile(); use the real one.
    $this->withVite();
    $hot = public_path('hot-test');
    file_put_contents($hot, 'http://127.0.0.1:5199');
    Vite::useHotFile($hot);

    try {
        $this->app->detectEnvironment(fn (): string => 'local');
        $local = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->app->detectEnvironment(fn (): string => 'production');
        $production = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
    } finally {
        @unlink($hot);
    }

    expect($local)->toContain('http://127.0.0.1:5199')->toContain('ws://127.0.0.1:5199')
        // Vite waits for a restarted dev server in a worker made from a blob,
        // then reloads the page; refused, the page stays on the old scripts.
        ->toContain("worker-src 'self' blob:")
        ->and($production)->not->toContain('5199')
        ->and($production)->not->toContain('worker-src');
});
