<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Support\FirstPasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Choose your own password": the page a person lands on after giving the
 * default password their account was opened with. They are not signed in
 * here; once their own password is saved they sign in with it.
 */
class FirstPasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = FirstPasswordChange::user($request);

        if ($user === null) {
            return to_route('login');
        }

        return Inertia::render('auth/first-password', ['email' => $user->email]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = FirstPasswordChange::user($request);

        if ($user === null) {
            return to_route('login');
        }

        $validated = $request->validate(['password' => $this->passwordRules()]);

        // Saving a new password takes the account off the default one (User::booted).
        $user->forceFill(['password' => $validated['password']])->save();

        FirstPasswordChange::forget($request);

        return to_route('login')->with('status', __('auth_ui.first_password.done'));
    }
}
