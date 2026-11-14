<?php

namespace App\Console\Commands;

use App\Actions\Permits\IssuePermit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Documents\StoredDocument;
use App\Enums\PermitStatus;
use App\Models\Permit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds again the PDF of issued, expired and revoked permits whose stored
 * document is missing or not a real PDF (written while the test renderer was
 * configured, or lost). The verification token is replaced at the same time,
 * because the PDF prints its QR code and only the token's hash is kept; a
 * document that could not be printed had no QR code anyone could scan.
 * Each rebuild is in the audit log.
 *
 * With --all the documents in order are rebuilt too, to print what changed
 * since (a new logo, new rules). Their QR codes change as well: a copy
 * printed before stops verifying, so the holder needs the new document.
 */
class RegenerateDocumentsCommand extends Command
{
    protected $signature = 'permits:documents
        {permit?* : Only these permits (permit number or id); all broken ones otherwise}
        {--all : Rebuild the documents in order too (new logo or rules); copies printed before stop verifying}
        {--dry-run : List what would be rebuilt, change nothing}
        {--now : Render at once in this process instead of leaving it to the queue worker}';

    protected $description = 'Rebuild permit PDFs that are missing or not real documents';

    public function handle(IssuePermit $issuePermit, AuditLog $audit): int
    {
        if (config('permits.pdf_renderer') === 'fake' && ! app()->environment('testing')) {
            $this->error('PERMITS_PDF_RENDERER is "fake": it would write placeholders again. Set it to "chrome" first.');

            return self::FAILURE;
        }

        $wanted = (array) $this->argument('permit');
        $all = (bool) $this->option('all');

        $permits = Permit::query()
            ->whereIn('status', [PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked])
            ->when($wanted !== [], fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('permit_number', $wanted)
                ->orWhereIn('id', $wanted)))
            ->orderBy('id')
            ->get()
            ->reject(fn (Permit $permit): bool => ! $all && StoredDocument::isUsable($permit));

        if ($permits->isEmpty()) {
            $this->info('Every permit document is in order.');

            return self::SUCCESS;
        }

        $this->table(
            ['Permit', 'Status', 'Document'],
            $permits->map(fn (Permit $permit): array => [
                $permit->permit_number,
                $permit->status->value,
                match (true) {
                    StoredDocument::paths($permit) === [] => 'none',
                    StoredDocument::isUsable($permit) => 'in order',
                    default => 'not a usable PDF',
                },
            ])->all(),
        );

        if ($all) {
            $this->warn('Documents in order get a new QR code: copies printed before stop verifying.');
        }

        if ($this->option('dry-run')) {
            $this->info("{$permits->count()} document(s) would be rebuilt.");

            return self::SUCCESS;
        }

        foreach ($permits as $permit) {
            $reason = StoredDocument::isUsable($permit) ? 'requested' : 'document_unusable';

            DB::transaction(function () use ($permit, $issuePermit, $audit, $reason): void {
                $issuePermit->handle($permit);
                $audit->record(AuditEvent::DocumentRegenerated, $permit, null, context: ['reason' => $reason]);
            });
        }

        if ($this->option('now')) {
            $this->call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
        } else {
            $this->line('Queued. A running queue worker renders them (composer dev runs one locally).');
        }

        $this->info("{$permits->count()} document(s) rebuilt.");

        return self::SUCCESS;
    }
}
