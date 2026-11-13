<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\FirstPasswordChange;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nobody uses the system on the default password. Signing in with it stops
 * at the "Choose your own password" page (FortifyServiceProvider); this is
 * the safety net for a session that is signed in all the same: it is ended
 * and the person is sent to that page.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            abort(Response::HTTP_FORBIDDEN, __('auth.password_change_required'));
        }

        FirstPasswordChange::begin($request, $user);

        return redirect()->route('first-password.show');
    }
}
