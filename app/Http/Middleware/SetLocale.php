<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale: the signed-in user's saved preference, then the
 * guest's session choice, then the application default (Amharic).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = match (true) {
            $user instanceof User => $user->locale,
            $request->hasSession() => $request->session()->get('locale'),
            default => null,
        };

        if (is_string($locale) && array_key_exists($locale, config('localization.supported'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
