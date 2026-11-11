<?php

namespace App\Jobs;

use App\Domain\Legacy\LegacyImporter;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs an import batch started from the Admin screen (spec §16: imports are
 * queued). One try: the import is idempotent, so an Admin can simply start
 * it again after fixing the cause of a failure.
 */
class RunLegacyImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $batchId) {}

    public function handle(LegacyImporter $importer): void
    {
        $batch = ImportBatch::query()->find($this->batchId);

        if ($batch === null || $batch->status !== ImportStatus::Pending || $batch->stored_path === null) {
            return;
        }

        // Copy the upload to a local file: the importer streams it row by row.
        $local = tempnam(sys_get_temp_dir(), 'legacy-import');
        $source = Storage::disk(config('permits.documents_disk'))->readStream($batch->stored_path);

        if ($local === false || $source === null) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $target = fopen($local, 'w');
        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);

        try {
            $importer->run($batch, $local);
        } finally {
            @unlink($local);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()
            ->whereKey($this->batchId)
            ->whereIn('status', [ImportStatus::Pending, ImportStatus::Running])
            ->update(['status' => ImportStatus::Failed, 'error' => mb_substr((string) $exception?->getMessage(), 0, 1000)]);
    }
}
