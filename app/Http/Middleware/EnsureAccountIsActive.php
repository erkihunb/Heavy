<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out someone whose account was deactivated while they were signed in
 * (DeactivateUser also ends the sessions it can find; this covers the rest,
 * whatever the session driver), and anyone signed in with an account that
 * is not for signing in (an applicant's: User::maySignIn()).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && (! $user->isActive() || ! $user->maySignIn())) {
            $reason = $user->isActive() ? 'auth.no_sign_in' : 'auth.deactivated';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __($reason)]);
        }

        return $next($request);
    }
}
