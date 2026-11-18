<?php

namespace App\Actions\Letters;

use App\Enums\VehicleRole;
use App\Exceptions\LetterVehicleRefused;
use App\Exceptions\PermitException;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\Vehicle;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Doing the same thing to several permits of one request letter: each by
 * the Action for one permit, so every rule still applies, all in one
 * transaction — a vehicle that is refused stops the lot, and the message
 * names it.
 */
final class LetterPermits
{
    /**
     * The letter's permits among those chosen, in the order they were asked for.
     *
     * @param  list<string>  $ids
     * @return list<Permit>
     *
     * @throws ValidationException when none of the chosen permits is the letter's
     */
    public function chosen(RequestLetter $letter, array $ids): array
    {
        $permits = $letter->permits()
            ->whereIn('id', $ids)
            ->with('vehicles')
            ->get()
            ->all();

        if ($permits === []) {
            throw ValidationException::withMessages(['permits' => __('letters.errors.none_chosen')]);
        }

        return $permits;
    }

    /**
     * @template T
     *
     * @param  list<Permit>  $permits
     * @param  Closure(Permit): T  $action
     * @return list<T>
     */
    public function each(array $permits, Closure $action): array
    {
        return DB::transaction(function () use ($permits, $action): array {
            $done = [];

            foreach ($permits as $permit) {
                try {
                    $done[] = $action($permit);
                } catch (PermitException $exception) {
                    throw new LetterVehicleRefused(self::plate($permit), $exception);
                }
            }

            return $done;
        });
    }

    /**
     * The plate of the permit's primary vehicle, by which the office knows it.
     */
    public static function plate(Permit $permit): string
    {
        $permit->loadMissing('vehicles');

        /** @var Vehicle|null $primary */
        $primary = $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Primary)
            ?? $permit->vehicles->first();

        return $primary === null ? (string) $permit->permit_number : $primary->plate_normalized;
    }

    /**
     * One vehicle of a letter as the office's pages list it: its plate, type
     * and trailers, where its permit stands, and a link to the permit's page
     * that the viewer may open.
     *
     * @return array{id: string, status: string, number: string|null, plate: string, type: string|null, trailers: list<string>, fee: string|null, fee_cents: int|null, recommendation: string|null, undecided: bool, url: string|null}
     */
    public static function row(Permit $permit): array
    {
        $locale = app()->getLocale();
        $vehicles = $permit->vehicles->sortBy(fn (Vehicle $vehicle): int => $vehicle->roleOnPermit() === VehicleRole::Primary ? 0 : 1)->values();
        /** @var Vehicle|null $primary */
        $primary = $vehicles->first();

        return [
            'id' => $permit->id,
            'status' => $permit->status->value,
            'number' => $permit->permit_number,
            'plate' => self::plate($permit),
            'type' => $primary?->typeName($locale),
            'trailers' => $vehicles->slice(1)->map(fn (Vehicle $vehicle): string => $vehicle->plate_normalized)->values()->all(),
            'fee' => $permit->fee_cents === null ? null : Money::format($permit->fee_cents, $permit->currency),
            'fee_cents' => $permit->fee_cents,
            'recommendation' => $permit->recommendation?->value,
            'undecided' => $permit->status->isAwaitingReview(),
            // The permit's own page: the review desk for those who review, else the Registrar's.
            'url' => Gate::inspect('view', $permit)->allowed()
                ? route('staff.permits.show', $permit)
                : (Gate::inspect('viewLetter', $permit)->allowed() ? route('admin.permits.show', $permit) : null),
        ];
    }
}
