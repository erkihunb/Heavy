<?php

namespace App\Actions\Permits;

use App\Domain\Permits\VerificationTokenService;
use App\Jobs\GeneratePermitDocument;
use App\Models\Permit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IssuePermit
{
    public function __construct(private readonly VerificationTokenService $tokens) {}

    /**
     * Give the permit a new verification token and queue its document
     * (spec §7, §8). Any earlier token stops working at once and the earlier
     * document is discarded. Meant to run inside the transaction that issues
     * or reissues the permit: the document is only queued once it commits.
     *
     * @return string the new token (it is not stored anywhere in clear)
     */
    public function handle(Permit $permit): string
    {
        ['token' => $token, 'hash' => $hash] = $this->tokens->generate();
        $oldDocument = $permit->document_path;

        $permit->verification_token_hash = $hash;
        $permit->document_path = null;
        $permit->save();

        DB::afterCommit(function () use ($oldDocument): void {
            if ($oldDocument !== null) {
                Storage::disk(config('permits.documents_disk'))->delete($oldDocument);
            }
        });

        GeneratePermitDocument::dispatch($permit->id, $token)->afterCommit();

        return $token;
    }
}
