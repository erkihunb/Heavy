<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Permits\SubmitPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Permit;
use App\Support\ReceiptFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Paying the fee of an approved permit: the applicant submits the receipt,
 * and can open the receipt file they uploaded.
 */
class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Permit $permit, SubmitPayment $submitPayment): RedirectResponse
    {
        $submitPayment->handle($permit, $request->user(), $request->toData());

        return to_route('permits.show', $permit)->with('success', __('payments.messages.submitted'));
    }

    public function receipt(Payment $payment): StreamedResponse
    {
        Gate::authorize('downloadReceipt', $payment);

        return ReceiptFile::stream($payment);
    }
}
