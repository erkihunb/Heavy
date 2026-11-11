<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log viewer (spec §11, §15): newest first, filtered by event and
 * by who made the change.
 */
class AuditLogController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAuditLog');

        $event = AuditEvent::tryFrom(is_string($request->query('event')) ? $request->query('event') : '');
        $actor = is_string($request->query('actor')) ? trim($request->query('actor')) : '';
        $locale = app()->getLocale();
        $timezone = (string) config('localization.display_timezone');

        $entries = Activity::query()
            ->where('log_name', AuditLog::LOG_NAME)
            ->with(['causer', 'subject'])
            ->when($event !== null, fn (Builder $query) => $query->where('event', $event->value))
            ->when($actor !== '', fn (Builder $query) => $query
                ->where('causer_type', (new User)->getMorphClass())
                ->whereIn('causer_id', User::query()
                    ->where(fn (Builder $query) => $query->whereLike('name', "%{$actor}%")->orWhereLike('email', "%{$actor}%"))
                    ->select('id')))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function (Activity $entry) use ($locale, $timezone): array {
                $causer = $entry->causer;
                $properties = $entry->properties;

                return [
                    'id' => $entry->id,
                    'event' => $entry->event,
                    'created_at' => EthiopianCalendar::present($entry->created_at?->setTimezone($timezone), $locale),
                    'created_time' => $entry->created_at?->setTimezone($timezone)->format('H:i:s'),
                    'actor' => $causer instanceof User ? ['name' => $causer->name, 'email' => $causer->email] : null,
                    'subject' => $this->subject($entry->subject, $locale),
                    'old' => (object) ($properties->get('old') ?? []),
                    'new' => (object) ($properties->get('attributes') ?? []),
                    'context' => (object) ($properties->get('context') ?? []),
                ];
            });

        return Inertia::render('admin/audit-log/index', [
            'entries' => Paginated::present($entries),
            'events' => array_map(fn (AuditEvent $event): string => $event->value, AuditEvent::cases()),
            'filters' => ['event' => $event?->value, 'actor' => $actor],
        ]);
    }

    /**
     * @return array{type: string, label: string, url: string|null}|null
     */
    private function subject(?Model $subject, string $locale): ?array
    {
        return match (true) {
            $subject instanceof Permit => ['type' => 'permit', 'label' => $subject->permit_number ?? $subject->id, 'url' => route('staff.permits.show', $subject)],
            $subject instanceof Payment => ['type' => 'payment', 'label' => $subject->receipt_number, 'url' => route('staff.payments.show', $subject)],
            $subject instanceof User => ['type' => 'user', 'label' => $subject->name, 'url' => route('admin.users.edit', $subject)],
            $subject instanceof VehicleType => ['type' => 'vehicle_type', 'label' => $subject->localizedName($locale), 'url' => route('admin.vehicle-types.edit', $subject)],
            $subject instanceof FeeSchedule => ['type' => 'fee_schedule', 'label' => '#'.$subject->id, 'url' => route('admin.fee-schedules.edit', $subject)],
            default => null,
        };
    }
}
