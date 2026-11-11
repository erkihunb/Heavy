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
        $choice = $request->cookie('appearance_mode');
        $default = (string) config('permits.appearance.default_mode', 'system');

        View::share('appearance', in_array($choice, ['light', 'dark', 'system'], true) ? $choice : $default);
        View::share('defaultAppearance', $default);
        View::share('theme', (string) config('permits.appearance.theme', 'forest'));

        return $next($request);
    }
}
