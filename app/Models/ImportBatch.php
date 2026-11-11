<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the legacy import (spec §13).
 *
 * @property int $id
 * @property string $file_name
 * @property string|null $stored_path
 * @property ImportStatus $status
 * @property bool $dry_run
 * @property array<string, int>|null $totals
 * @property int $started_by
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 */
class ImportBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['file_name', 'dry_run'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'dry_run' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'dry_run' => 'boolean',
            'totals' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return HasMany<ImportIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(ImportIssue::class)->orderBy('source_row')->orderBy('id');
    }

    /**
     * Where the issues CSV of this batch is written on the documents disk.
     */
    public function issuesPath(): string
    {
        return "imports/batch-{$this->id}-issues.csv";
    }
}
