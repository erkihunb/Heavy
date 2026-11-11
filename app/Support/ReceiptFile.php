<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a payment's receipt file from the private documents disk. Callers
 * authorise the request first; the stored path never reaches the browser.
 */
final class ReceiptFile
{
    public static function stream(Payment $payment): StreamedResponse
    {
        $disk = Storage::disk(config('permits.documents_disk'));

        abort_if($payment->receipt_file_path === null || ! $disk->exists($payment->receipt_file_path), 404);

        $extension = pathinfo($payment->receipt_file_path, PATHINFO_EXTENSION);
        // A neutral name: no applicant data, no internal id.
        $name = 'receipt-'.preg_replace('/[^A-Za-z0-9\-]/', '-', $payment->receipt_number).'.'.$extension;

        return $disk->response($payment->receipt_file_path, $name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
