<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A person's own choice, otherwise the default an Admin set (System settings → Appearance).
        // When people may not choose (allow_user_mode off), the default always applies.
        $choice = config('permits.appearance.allow_user_mode', true) ? $request->cookie('appearance_mode') : null;
        $default = (string) config('permits.appearance.default_mode', 'system');

        View::share('appearance', in_array($choice, ['light', 'dark', 'system'], true) ? $choice : $default);
        View::share('defaultAppearance', $default);

        return $next($request);
    }
}
