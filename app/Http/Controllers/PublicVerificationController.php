<?php

namespace App\Http\Controllers;

use App\Domain\Permits\PermitVerifier;
use App\Domain\Verification\ScanLogger;
use App\Enums\ScanChannel;
use App\Http\Requests\Verification\PublicLookupRequest;
use App\Http\Resources\OfficerPermitVerificationResource;
use App\Http\Resources\PublicPermitVerificationResource;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checking a permit (spec §7). The QR code points here. Anyone gets a plain,
 * server-rendered page that works without JavaScript; a signed-in traffic
 * officer gets the officer view of the same permit instead. Every check is
 * logged.
 */
class PublicVerificationController extends Controller
{
    public function __construct(
        private readonly PermitVerifier $verifier,
        private readonly ScanLogger $scans,
    ) {}

    public function token(Request $request, string $token): View|Response
    {
        $permit = $this->verifier->byToken($token);

        return $this->answer($request, $permit, ScanChannel::Qr);
    }

    /**
     * The public look-up form. A signed-in officer is sent to their own
     * look-up page instead: an officer who looked a permit up by number and
     * plate is at this address, so anything that redirects them "back" (a
     * saved report, a refused one) lands here, and this plain page cannot be
     * shown inside the back office. Whatever was flashed goes with them.
     */
    public function form(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->can('scan', Permit::class)) {
            $request->session()->reflash();

            return redirect()->route('officer.verify');
        }

        return view('verification.lookup');
    }

    public function lookup(PublicLookupRequest $request): View|Response
    {
        $permit = $this->verifier->byNumberAndPlate(
            $request->string('permit_number')->toString(),
            $request->string('plate')->toString(),
        );

        return $this->answer($request, $permit, ScanChannel::Lookup);
    }

    private function answer(Request $request, ?Permit $permit, ScanChannel $channel): View|Response
    {
        $result = $this->verifier->result($permit);
        $locale = app()->getLocale();
        $user = $request->user();
        $officer = $user instanceof User && $user->can('scan', Permit::class) ? $user : null;

        $this->scans->log($permit, $result, $channel, $request, $officer);

        if ($officer !== null) {
            return Inertia::render('officer/scan', [
                'verification' => OfficerPermitVerificationResource::make($permit, $result, $locale),
            ]);
        }

        return view('verification.result', [
            'verification' => PublicPermitVerificationResource::make($permit, $result, $locale),
        ]);
    }
}
