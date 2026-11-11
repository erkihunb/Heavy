<?php

namespace App\Models;

use App\Enums\ExportDataset;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A spreadsheet export requested by a member of staff (spec §11).
 *
 * @property int $id
 * @property int $user_id
 * @property ExportDataset $dataset
 * @property ExportFormat $format
 * @property array{from?: string|null, to?: string|null, status?: string|null}|null $filters
 * @property ExportStatus $status
 * @property string|null $path
 * @property int|null $rows
 * @property string|null $error
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 */
class Export extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['dataset', 'format', 'filters'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dataset' => ExportDataset::class,
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'filters' => 'array',
            'rows' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The file name the person downloads.
     */
    public function downloadName(): string
    {
        return sprintf('%s-%s-%d.%s', $this->dataset->value, $this->created_at?->format('Ymd') ?? 'export', $this->id, $this->format->value);
    }
}
