<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Stored in the session for guests and
     * additionally persisted on the account for signed-in users.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->locale();

        $request->session()->put('locale', $locale);

        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
