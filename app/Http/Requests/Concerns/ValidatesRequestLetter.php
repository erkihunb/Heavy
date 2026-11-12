<?php

namespace App\Http\Requests\Concerns;

use App\Data\LetterData;
use App\Data\PermitData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * The letter a request came in with: its reference number, its date and a
 * scanned copy (PDF or image). The scan may be left out of an edit when one
 * is already stored.
 *
 * @mixin FormRequest
 */
trait ValidatesRequestLetter
{
    /**
     * @param  list<string>  $condition  rules put first, e.g. ['exclude_unless:apply,true']
     * @return array<string, list<mixed>>
     */
    protected function letterRules(bool $scanRequired, array $condition = []): array
    {
        return [
            'letter_number' => [...$condition, 'required', 'string', 'max:64'],
            'letter_date' => [...$condition, 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'letter_file' => [
                ...$condition,
                $scanRequired ? 'required' : 'nullable',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('permits.receipt_max_kilobytes')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function letterAttributes(): array
    {
        return [
            'letter_number' => __('permits.letter.number'),
            'letter_date' => __('permits.letter.date'),
            'letter_file' => __('permits.letter.file'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function letterMessages(): array
    {
        return [
            'letter_file.mimes' => __('permits.letter.file_type'),
            'letter_file.max' => __('permits.letter.file_size', ['max' => intdiv((int) config('permits.receipt_max_kilobytes'), 1024)]),
            'letter_date.before_or_equal' => __('permits.letter.date_future'),
        ];
    }

    /**
     * The application details with the letter added.
     */
    protected function withLetter(PermitData $data): PermitData
    {
        return new PermitData(
            permitType: $data->permitType,
            primaryVehicleId: $data->primaryVehicleId,
            trailerVehicleIds: $data->trailerVehicleIds,
            validFrom: $data->validFrom,
            validTo: $data->validTo,
            reason: $data->reason,
            branchId: $data->branchId,
            hours: $data->hours,
            letter: $this->letter(),
        );
    }

    protected function letter(): LetterData
    {
        $file = $this->file('letter_file');

        return new LetterData(
            number: trim((string) $this->input('letter_number')),
            date: CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('letter_date'), 'UTC') ?: CarbonImmutable::today('UTC'),
            file: $file instanceof UploadedFile ? $file : null,
        );
    }
}
