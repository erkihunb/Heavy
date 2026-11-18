<?php

namespace App\Actions\Letters;

use App\Enums\VehicleRole;
use App\Exceptions\LetterVehicleRefused;
use App\Exceptions\PermitException;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\Vehicle;
use Closure;
use Illuminate\Support\Facades\DB;
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
}
