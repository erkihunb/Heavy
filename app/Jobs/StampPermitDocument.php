<?php

namespace App\Jobs;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Documents\CertificateCode;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Domain\Documents\StoredDocument;
use App\Enums\PermitStatus;
use App\Models\Permit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prints a permit's PDFs again once it is no longer in force, stamped across
 * the page in both languages: "የተሰረዘ" / "REVOKED" when it was revoked,
 * "ጊዜው ያለፈ" / "EXPIRED" when its period ended. A copy exported afterwards
 * cannot then pass for a valid permit.
 *
 * The verification token is not known any more (only its hash is kept) and
 * is left as it is: a printed copy's QR code keeps answering "revoked" or
 * "expired". The new PDFs therefore show the stamp where the QR code was.
 *
 * Idempotent: it does nothing unless the permit is still revoked or expired,
 * and the old files are removed only once the new ones are attached.
 */
class StampPermitDocument implements ShouldQueue
{
    use Queueable;

    /**
     * The statuses whose PDFs are stamped.
     *
     * @var list<PermitStatus>
     */
    public const array STATUSES = [PermitStatus::Revoked, PermitStatus::Expired];

    public int $tries = 3;

    /**
     * Seconds to wait before each retry: the renderer may be restarting.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public int $timeout = 120;

    public function __construct(public readonly string $permitId) {}

    public function handle(PdfRenderer $renderer): void
    {
        $permit = Permit::query()->whereKey($this->permitId)->whereIn('status', self::STATUSES)->first();

        // Nothing printed yet, nothing to stamp: `permits:documents` prints it stamped later.
        if ($permit === null || StoredDocument::paths($permit) === []) {
            return;
        }

        $disk = Storage::disk(config('permits.documents_disk'));
        $old = StoredDocument::paths($permit);
        $paths = [];

        try {
            foreach (StoredDocument::LANGUAGES as $language => $column) {
                $pdf = $renderer->render(PermitDocument::html($permit, null, $language));
                $path = 'permits/'.Str::random(40).'.pdf';

                if (! $disk->put($path, $pdf)) {
                    throw new \RuntimeException('The stamped permit document could not be stored.');
                }

                $paths[$column] = $path;
            }
        } catch (Throwable $exception) {
            $disk->delete(array_values($paths));

            throw $exception;
        }

        // Attach them only if the permit is still in the same state (not reissued meanwhile).
        $attached = Permit::query()
            ->whereKey($permit->id)
            ->where('status', $permit->status)
            // The certificate code printed on these PDFs, kept as printed.
            ->update([...$paths, 'certificate_code' => CertificateCode::for($permit)]);

        $disk->delete($attached === 0 ? array_values($paths) : $old);

        if ($attached > 0) {
            // The system reprinted it: no person did this.
            app(AuditLog::class)->record(AuditEvent::DocumentStamped, $permit, null, context: [
                'stamp' => $permit->status->value,
                'certificate_code' => CertificateCode::for($permit),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Stamped permit document could not be generated.', [
            'permit_id' => $this->permitId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
