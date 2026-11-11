<?php

namespace App\Jobs;

use App\Domain\Documents\PdfRenderer;
use App\Domain\Documents\PermitDocument;
use App\Domain\Permits\VerificationTokenService;
use App\Models\Permit;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renders a permit's PDF and stores it on the private documents disk
 * (spec §8). The job carries the verification token because only its hash is
 * stored; the payload is therefore encrypted on the queue.
 *
 * Idempotent: if the token has been rotated since the job was queued (the
 * permit was reissued), it does nothing, and a document is attached only if
 * the token still matches when it is saved.
 */
class GeneratePermitDocument implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds to wait before each retry: the renderer may be restarting.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 600];

    public int $timeout = 120;

    public function __construct(
        public readonly string $permitId,
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    public function handle(PdfRenderer $renderer): void
    {
        $hash = VerificationTokenService::hash($this->token);
        $permit = Permit::query()->whereKey($this->permitId)->where('verification_token_hash', $hash)->first();

        if ($permit === null) {
            return;
        }

        $pdf = $renderer->render(PermitDocument::html($permit, $this->token));

        $disk = Storage::disk(config('permits.documents_disk'));
        $path = 'permits/'.Str::random(40).'.pdf';

        if (! $disk->put($path, $pdf)) {
            throw new \RuntimeException('The permit document could not be stored.');
        }

        // Attach it only if the permit was not reissued while rendering.
        $attached = Permit::query()
            ->whereKey($permit->id)
            ->where('verification_token_hash', $hash)
            ->update(['document_path' => $path]);

        if ($attached === 0) {
            $disk->delete($path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Never log the token.
        Log::error('Permit document could not be generated.', [
            'permit_id' => $this->permitId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
