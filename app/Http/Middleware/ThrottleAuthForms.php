<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits for the Fortify forms that Fortify itself does not limit
 * (spec §15): registration, asking for a password-reset e-mail, resetting the
 * password and confirming it. Login, two-factor and the verification e-mail
 * already have their own limits.
 */
class ThrottleAuthForms
{
    /** @var list<string> */
    public const array ROUTES = ['register.store', 'password.email', 'password.update', 'password.confirm.store', 'first-password.store'];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && in_array($request->route()?->getName(), self::ROUTES, true)) {
            return $this->throttle->handle($request, $next, 'auth-forms');
        }

        return $next($request);
    }
}
