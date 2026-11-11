<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (spec §15). The Content-Security-Policy
 * allows scripts only from this site and those carrying the request's nonce
 * (Vite adds it to the tags it prints). Styles may be inline because React
 * sets style attributes. A header a route has already set (the verification
 * pages send `Referrer-Policy: no-referrer`) is kept.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($request, $nonce),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        // The Vite dev server serves scripts and hot updates from another origin.
        $dev = app()->isLocal() ? $this->viteDevServer() : null;
        $devSocket = $dev === null ? null : preg_replace('#^http#', 'ws', $dev);

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $dev],
            'style-src' => ["'self'", "'unsafe-inline'", $dev],
            'img-src' => ["'self'", 'data:', 'blob:', $dev],
            'font-src' => ["'self'", 'data:', $dev],
            'connect-src' => ["'self'", $dev, $devSocket],
            'frame-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');

        if ($request->isSecure() && app()->isProduction()) {
            $policy .= '; upgrade-insecure-requests';
        }

        return $policy;
    }

    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(Vite::hotFile()));

        return preg_match('#^https?://[^/\s]+$#', $url) === 1 ? $url : null;
    }
}
