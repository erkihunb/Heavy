<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Permits\RejectPayment;
use App\Actions\Permits\VerifyPayment;
use App\Domain\Payments\ReceiptNumber;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RejectPaymentRequest;
use App\Http\Requests\Staff\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\ReceiptFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cashier's work: payments waiting for verification, and the decision on
 * each one.
 */
class PaymentController extends Controller
{
    private const int PER_PAGE = 20;

    /** The `status` filter value that lifts the default "waiting" filter. */
    private const string ALL_STATUSES = 'all';

    public function index(Request $request): Response
    {
        Gate::authorize('viewQueue', Payment::class);

        $statusInput = is_string($request->query('status')) ? $request->query('status') : '';
        $all = $statusInput === self::ALL_STATUSES;
        $status = $all ? null : (PaymentStatus::tryFrom($statusInput) ?? PaymentStatus::Submitted);
        $receipt = is_string($request->query('receipt')) ? ReceiptNumber::normalize($request->query('receipt')) : '';

        $payments = Payment::query()
            ->with(['permit.organization', 'permit.vehicles.vehicleType', 'submitter', 'verifier'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($receipt !== '', fn (Builder $query) => $query->whereLike('receipt_number', '%'.addcslashes($receipt, '%_\\').'%'))
            // Oldest first: the payment that has waited longest is on top.
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('staff/payments/index', [
            'payments' => PaymentResource::collection($payments),
            'filters' => [
                'status' => $status === null ? self::ALL_STATUSES : $status->value,
                'receipt' => $receipt,
            ],
        ]);
    }

    public function show(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load([
            'submitter',
            'verifier',
            'permit.organization',
            'permit.vehicles.vehicleType',
            'permit.payments',
            'permit.transitions.actor',
        ]);

        return Inertia::render('staff/payments/show', [
            'payment' => PaymentResource::make($payment),
            // Separation of duties, for the explanation on the page; enforced in VerifyPayment.
            'approvedByViewer' => (bool) config('permits.separation_of_duties')
                && $payment->permit->approved_by === request()->user()?->id,
        ]);
    }

    public function verify(VerifyPaymentRequest $request, Payment $payment, VerifyPayment $verifyPayment): RedirectResponse
    {
        $verifyPayment->handle($payment, $request->user(), $request->note());

        return to_route('staff.payments.show', $payment)->with('success', __('payments.messages.verified'));
    }

    public function reject(RejectPaymentRequest $request, Payment $payment, RejectPayment $rejectPayment): RedirectResponse
    {
        $rejectPayment->handle($payment, $request->user(), (string) $request->note());

        return to_route('staff.payments.show', $payment)->with('success', __('payments.messages.rejected'));
    }

    public function receipt(Payment $payment): StreamedResponse
    {
        Gate::authorize('downloadReceipt', $payment);

        return ReceiptFile::stream($payment);
    }
}
