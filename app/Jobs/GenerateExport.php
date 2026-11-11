<?php

namespace App\Jobs;

use App\Domain\Reports\ExportDatasets;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\Export;
use App\Notifications\ExportReady;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Builds a requested export on the queue (spec §11, §16) with openspout,
 * which streams rows to disk, stores it on the private documents disk and
 * tells the requester it is ready. Runs in the requester's language.
 */
class GenerateExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [60];

    public int $timeout = 1800;

    public function __construct(public readonly int $exportId) {}

    public function handle(ExportDatasets $datasets): void
    {
        $export = Export::query()->with('user')->find($this->exportId);

        if ($export === null || $export->status === ExportStatus::Completed) {
            return;
        }

        $export->forceFill(['status' => ExportStatus::Running, 'error' => null])->save();

        $previous = app()->getLocale();
        app()->setLocale($export->user->preferredLocale());

        $local = tempnam(sys_get_temp_dir(), 'export');

        try {
            $writer = $this->writer($export->format);
            $writer->openToFile($local);
            $writer->addRow(Row::fromValues($datasets->header($export)));

            $rows = 0;

            foreach ($datasets->rows($export) as $values) {
                $writer->addRow(Row::fromValues($values));
                $rows++;
            }

            $writer->close();

            $path = 'exports/'.Str::random(40).'.'.$export->format->value;
            $stream = fopen($local, 'r');
            Storage::disk(config('permits.documents_disk'))->put($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $export->forceFill([
                'status' => ExportStatus::Completed,
                'path' => $path,
                'rows' => $rows,
                'finished_at' => CarbonImmutable::now(),
            ])->save();
        } finally {
            @unlink((string) $local);
            app()->setLocale($previous);
        }

        $export->user->notify(new ExportReady($export));
    }

    public function failed(?Throwable $exception): void
    {
        Export::query()->whereKey($this->exportId)->update([
            'status' => ExportStatus::Failed,
            'error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
            'finished_at' => CarbonImmutable::now(),
        ]);
    }

    private function writer(ExportFormat $format): WriterInterface
    {
        // CSV gets a UTF-8 byte-order mark so spreadsheet programs show Ethiopic text.
        return $format === ExportFormat::Xlsx ? new XlsxWriter : new CsvWriter;
    }
}
