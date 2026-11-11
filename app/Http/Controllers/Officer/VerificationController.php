<?php

namespace App\Http\Controllers\Officer;

use App\Actions\Verification\ReportPermit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Officer\ReportPermitRequest;
use App\Models\Permit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The traffic officer's tools (spec §7): looking a permit up by number and
 * plate, and reporting a violation. Scanning a QR code opens the public
 * address, which shows officers this same view.
 */
class VerificationController extends Controller
{
    public function lookup(): Response
    {
        Gate::authorize('scan', Permit::class);

        return Inertia::render('officer/lookup', [
            // QR codes point to <verify_url>/v/<token>; the scanner accepts only those.
            'verifyBase' => rtrim((string) config('permits.verify_url'), '/'),
        ]);
    }

    public function report(ReportPermitRequest $request, Permit $permit, ReportPermit $reportPermit): RedirectResponse
    {
        $reportPermit->handle($permit, $request->user(), $request->string('note')->toString());

        return back()->with('success', __('verification.officer.report.saved'));
    }
}
