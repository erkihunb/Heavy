<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines staff users to the two-factor setup screens until they have
 * confirmed two-factor authentication (spec §2: staff must use 2FA).
 */
class RequireTwoFactorForStaff
{
    /**
     * Routes a staff member must still reach in order to set up 2FA, confirm
     * their password, verify their e-mail, switch language or sign out.
     *
     * @var list<string>
     */
    private const array EXEMPT_ROUTES = [
        'two-factor.*',
        'password.confirm',
        'password.confirm.*',
        'password.confirmation',
        'verification.*',
        'locale.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustEnableTwoFactor() || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            abort(Response::HTTP_FORBIDDEN, __('auth.two_factor_required'));
        }

        return redirect()->route('two-factor.show');
    }
}
