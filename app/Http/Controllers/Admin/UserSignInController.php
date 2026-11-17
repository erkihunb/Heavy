<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ResetUserSignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetTemporaryPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A user's sign-in, for whoever holds users.reset-passwords (UserPolicy::resetPassword).
 */
class UserSignInController extends Controller
{
    public function password(SetTemporaryPasswordRequest $request, User $user, ResetUserSignIn $reset): RedirectResponse
    {
        $reset->setTemporaryPassword($user, $request->string('password')->toString(), $request->user());

        return back()->with('success', __('rbac.users.messages.password_set'));
    }

    public function resetLink(Request $request, User $user, ResetUserSignIn $reset): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $reset->sendResetLink($user, $request->user());

        return back()->with('success', __('rbac.users.messages.reset_link_sent', ['email' => $user->email]));
    }

    public function twoFactor(Request $request, User $user, ResetUserSignIn $reset): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $reset->resetTwoFactor($user, $request->user());

        return back()->with('success', __('rbac.users.messages.two_factor_reset'));
    }
}
