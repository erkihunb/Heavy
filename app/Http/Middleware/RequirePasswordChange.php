<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines someone who still signs in with the default password to the
 * password screen until they have chosen their own. Runs before
 * RequireTwoFactorForStaff: the password comes first, then two-factor.
 */
class RequirePasswordChange
{
    /**
     * Routes the person must still reach to change the password, switch
     * language or sign out.
     *
     * @var list<string>
     */
    private const array EXEMPT_ROUTES = [
        'user-password.*',
        'locale.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            abort(Response::HTTP_FORBIDDEN, __('auth.password_change_required'));
        }

        return redirect()->route('user-password.edit');
    }
}
