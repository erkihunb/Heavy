<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vehicles (and later applications) belong to an organization, so an applicant
 * who has not created their profile yet is sent to do that first.
 */
class EnsureUserHasOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->organization_id === null) {
            return redirect()
                ->route('organization.show')
                ->with('warning', __('organization.required'));
        }

        return $next($request);
    }
}
