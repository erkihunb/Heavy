<?php

namespace App\Http\Controllers;

use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The signed-in user's home screen.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'account' => [
                'emailVerified' => $user->hasVerifiedEmail(),
                'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            ],
            // Where an applicant stands in setting up their register; null for everyone else.
            'registration' => $user->can('viewAny', Vehicle::class) ? [
                'hasOrganization' => $user->organization_id !== null,
                'vehicleCount' => $user->organization_id === null
                    ? 0
                    : Vehicle::query()->where('organization_id', $user->organization_id)->count(),
            ] : null,
            // Where permits are worked on for this role: the applicant's own
            // list, the review queue (with how much is waiting), or nowhere yet.
            'permits' => [
                'canApply' => $user->can('viewAny', Permit::class) && $user->organization_id !== null,
                'waitingForReview' => $user->can('viewQueue', Permit::class)
                    ? Permit::query()->whereIn('status', [PermitStatus::Submitted, PermitStatus::UnderReview])->count()
                    : null,
            ],
        ]);
    }
}
