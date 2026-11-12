<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Permits\CommentOnPermit;
use App\Actions\Permits\IssuePaidPermit;
use App\Actions\Permits\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CommentOnPermitRequest;
use App\Http\Requests\Staff\IssuePermitRequest;
use App\Http\Requests\Staff\RecordPaymentRequest;
use App\Models\Permit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The office desks after the decision: the cashier records the payment, the
 * Issuer issues and prints the permit, and the head office comments. Also
 * the scanned request letter, for staff who can see the application.
 */
class PermitDeskController extends Controller
{
    public function recordPayment(RecordPaymentRequest $request, Permit $permit, RecordPayment $recordPayment): RedirectResponse
    {
        $recordPayment->handle($permit, $request->user(), $request->toData(), $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.payment_recorded'));
    }

    public function issue(IssuePermitRequest $request, Permit $permit, IssuePaidPermit $issuePaidPermit): RedirectResponse
    {
        $issuePaidPermit->handle($permit, $request->user(), $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.issued'));
    }

    public function comment(CommentOnPermitRequest $request, Permit $permit, CommentOnPermit $commentOnPermit): RedirectResponse
    {
        $commentOnPermit->handle($permit, $request->user(), $request->body());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.commented'));
    }

    public function letter(Permit $permit): StreamedResponse
    {
        Gate::authorize('viewLetter', $permit);

        $disk = Storage::disk(config('permits.documents_disk'));
        $path = $permit->letter_file_path;

        abort_if($path === null || ! $disk->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = 'letter-'.preg_replace('/[^A-Za-z0-9-]+/', '-', $permit->letter_number ?? $permit->id).'.'.$extension;

        // Shown in the browser (PDF or image), never run: the type comes from the stored content.
        return $disk->response($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
