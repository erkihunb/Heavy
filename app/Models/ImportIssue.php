<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of the legacy CSV that could not be imported cleanly: which line,
 * which field, the value as it was, and why.
 *
 * @property int $id
 * @property int $import_batch_id
 * @property int $source_row
 * @property string $field
 * @property string|null $raw_value
 * @property string $message
 * @property IssueSeverity $severity
 */
class ImportIssue extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['source_row', 'field', 'raw_value', 'message', 'severity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
            'severity' => IssueSeverity::class,
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
