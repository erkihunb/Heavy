<?php

namespace App\Domain\Permits;

use App\Data\LetterData;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The scanned request letters, on the private documents disk under a random
 * name. A Registrar stores one with each request taken at the office.
 */
final class RequestLetters
{
    /**
     * Store the scanned letter, if one was sent. Call before the transaction
     * that records it, and discard() the path if that transaction fails.
     *
     * @throws PermitRuleViolation
     */
    public function store(LetterData $letter): ?string
    {
        if ($letter->file === null) {
            return null;
        }

        $extension = $letter->file->guessExtension() ?? 'bin';
        $path = Storage::disk(config('permits.documents_disk'))->putFileAs('letters', $letter->file, Str::random(40).'.'.$extension);

        if ($path === false) {
            throw new PermitRuleViolation('letter_not_stored');
        }

        return $path;
    }

    /**
     * Record the letter on the permit (inside the transaction). A letter
     * replaced by a new scan is deleted once the change commits.
     */
    public function attach(Permit $permit, LetterData $letter, ?string $path): void
    {
        $previous = $permit->letter_file_path;

        $permit->letter_number = trim($letter->number);
        $permit->letter_date = $letter->date;

        if ($path !== null) {
            $permit->letter_file_path = $path;

            if ($previous !== null && $previous !== $path) {
                DB::afterCommit(fn () => $this->discard($previous));
            }
        }

        $permit->save();
    }

    public function discard(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(config('permits.documents_disk'))->delete($path);
        }
    }
}
