<?php

namespace App\Http\Resources;

use App\Models\ImportBatch;
use App\Support\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * An import run as an Admin sees it. The stored upload path and the issues
 * themselves stay on the server; the issues are downloaded as a CSV.
 *
 * @mixin ImportBatch
 */
class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = config('localization.display_timezone');
        $createdAt = $this->created_at?->setTimezone($timezone);

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'status' => $this->status->value,
            'dry_run' => $this->dry_run,
            'totals' => $this->totals,
            'started_by' => $this->whenLoaded('starter', fn (): string => $this->starter->name),
            'created_at' => EthiopianCalendar::present($createdAt, app()->getLocale()),
            'created_time' => $createdAt?->format('H:i'),
            'error' => $this->error,
            'has_issues_file' => Storage::disk(config('permits.documents_disk'))->exists($this->issuesPath()),
        ];
    }
}
