<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportsCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete exports (and their files) older than permits.exports.keep_days';

    public function handle(): int
    {
        $disk = Storage::disk(config('permits.documents_disk'));
        $count = 0;

        Export::query()
            ->where('created_at', '<', now()->subDays((int) config('permits.exports.keep_days')))
            ->orderBy('id')
            ->each(function (Export $export) use ($disk, &$count): void {
                if ($export->path !== null) {
                    $disk->delete($export->path);
                }

                $export->delete();
                $count++;
            });

        $this->info("Deleted {$count} export(s).");

        return self::SUCCESS;
    }
}
