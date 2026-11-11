<?php

namespace App\Domain\Audit;

use App\Domain\Verification\ScanLogger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit log (spec §15): who did what, to what, when, with the values
 * before and after. Written explicitly by the Actions that make the change,
 * never by model events, so that every entry is a decision someone made.
 * The table is append-only (database triggers). The IP address is kept only
 * as a keyed hash, like the scan log.
 */
final class AuditLog
{
    public const string LOG_NAME = 'audit';

    /**
     * @param  User|null  $actor  null when the system made the change (scheduled job, import)
     * @param  array<string, mixed>  $old  the values before the change
     * @param  array<string, mixed>  $new  the values after the change
     * @param  array<string, mixed>  $context  anything else worth keeping (never personal data)
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        ?User $actor = null,
        array $old = [],
        array $new = [],
        array $context = [],
    ): void {
        $ip = request()->ip();

        $properties = array_filter([
            'old' => $old,
            'attributes' => $new,
            'context' => $context,
            'ip_hash' => $ip === null ? null : ScanLogger::hashIp($ip),
        ], fn (mixed $value): bool => $value !== [] && $value !== null);

        $logger = activity(self::LOG_NAME)->event($event->value)->withProperties($properties);

        $logger = $actor === null ? $logger->causedByAnonymous() : $logger->causedBy($actor);

        if ($subject !== null) {
            $logger = $logger->performedOn($subject);
        }

        $logger->log($event->value);
    }
}
