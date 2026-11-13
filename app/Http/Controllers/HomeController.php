<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * The site root has no page of its own: guests go straight to signing
     * in, signed-in people to their dashboard.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }
}
