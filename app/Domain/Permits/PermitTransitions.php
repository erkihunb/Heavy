<?php

namespace App\Domain\Permits;

use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Models\Permit;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one way a permit changes status (spec §3). Inside a transaction it
 * locks the permit row, asks the state machine, lets the Action make its own
 * changes, saves, brings the vehicle rows in line and appends the history row.
 */
final class PermitTransitions
{
    public function __construct(
        private readonly PermitStateMachine $stateMachine,
        private readonly PermitVehicles $vehicles,
    ) {}

    /**
     * @param  User|null  $actor  null when the system makes the change
     * @param  (Closure(Permit): void)|null  $changes  checks and attribute changes that belong to
     *                                                 this transition; runs with the row locked
     *
     * @throws InvalidPermitTransition
     * @throws QueryException when the database refuses the change, e.g. the exclusion constraint on permit_vehicles
     */
    public function apply(Permit $permit, PermitStatus $to, ?User $actor, ?string $note = null, ?Closure $changes = null): Permit
    {
        return DB::transaction(function () use ($permit, $to, $actor, $note, $changes): Permit {
            $this->lock($permit);

            $from = $permit->status;
            $this->stateMachine->assertCanTransition($from, $to);

            if ($changes !== null) {
                $changes($permit);
            }

            $permit->status = $to;
            $permit->save();

            $this->vehicles->sync($permit);
            $this->record($permit, $from, $to, $actor, $note);

            return $permit;
        });
    }

    /**
     * Lock the permit row until the surrounding transaction ends and reload
     * the model, so every decision is made on current data.
     */
    public function lock(Permit $permit): void
    {
        Permit::query()->whereKey($permit->id)->lockForUpdate()->firstOrFail(['id']);

        $permit->refresh();
    }

    /**
     * Append a row to the history of the permit.
     */
    public function record(Permit $permit, ?PermitStatus $from, PermitStatus $to, ?User $actor, ?string $note = null): void
    {
        $permit->transitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => filled($note) ? trim((string) $note) : null,
        ]);

        $permit->unsetRelation('transitions');
    }
}
