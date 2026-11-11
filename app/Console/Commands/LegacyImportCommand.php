<?php

namespace App\Console\Commands;

use App\Domain\Legacy\LegacyImporter;
use App\Enums\ImportStatus;
use App\Enums\Role;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import
        {file : CSV exported from the Google Sheet "Form Responses 1"}
        {--dry-run : Read and check everything, report the issues, write no permits}
        {--batch= : Run an existing pending import batch (created by the Admin screen)}
        {--user= : E-mail of the Admin running the import (default: the first Admin)}
        {--timestamp-format=* : PHP date format(s) of the Timestamp column, tried in order}';

    protected $description = 'Import the legacy permits from the Google Form CSV (idempotent)';

    public function handle(LegacyImporter $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $batch = $this->batch(basename($path));

        if ($batch === null) {
            return self::FAILURE;
        }

        $this->info(($batch->dry_run ? 'Dry run of' : 'Importing')." {$batch->file_name} as batch #{$batch->id}…");

        /** @var list<string> $formats */
        $formats = (array) $this->option('timestamp-format');
        $totals = $importer->run($batch, $path, $formats === [] ? null : $formats);

        $this->table(['', 'count'], collect($totals)->map(fn (int $count, string $key): array => [str_replace('_', ' ', $key), $count])->values()->all());

        $issues = Storage::disk(config('permits.documents_disk'))->path($batch->issuesPath());
        $this->line("Issues CSV: {$issues}");

        if ($batch->dry_run) {
            $this->warn('Dry run: nothing was imported.');
        }

        return self::SUCCESS;
    }

    private function batch(string $fileName): ?ImportBatch
    {
        if ($this->option('batch') !== null) {
            $batch = ImportBatch::query()->find($this->option('batch'));

            if ($batch === null || $batch->status !== ImportStatus::Pending) {
                $this->error('Batch #'.$this->option('batch').' does not exist or has already run.');

                return null;
            }

            return $batch;
        }

        $user = $this->option('user') !== null
            ? User::query()->where('email', $this->option('user'))->first()
            : User::role(Role::Admin->value)->orderBy('id')->first();

        if ($user === null || ! $user->can('create', ImportBatch::class)) {
            $this->error('Give the e-mail of an Admin with --user (no Admin account was found).');

            return null;
        }

        $batch = new ImportBatch(['file_name' => $fileName, 'dry_run' => (bool) $this->option('dry-run')]);
        $batch->started_by = $user->id;
        $batch->save();

        return $batch;
    }
}
