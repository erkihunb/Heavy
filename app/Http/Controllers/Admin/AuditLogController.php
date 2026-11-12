<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * The audit log viewer (spec §11, §15): newest first, filtered by event (by
 * area), by who made the change, by what it concerns (a permit number, plate,
 * receipt, person or branch) and by date. Each changed field comes with its
 * label in the reader's language, and settings keys with the label they have
 * in System settings.
 */
class AuditLogController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAuditLog');

        $event = AuditEvent::tryFrom($this->text($request, 'event'));
        $actor = $this->text($request, 'actor');
        $subject = $this->text($request, 'subject');
        $from = $this->date($this->text($request, 'from'));
        $to = $this->date($this->text($request, 'to'));
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
            ->when($subject !== '', fn (Builder $query) => $this->concerning($query, $subject))
            // The dates are the reader's calendar days, in the display timezone.
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from->setTimezone($timezone)->startOfDay()->utc()))
            ->when($to !== null, fn (Builder $query) => $query->where('created_at', '<=', $to->setTimezone($timezone)->endOfDay()->utc()))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function (Activity $entry) use ($locale, $timezone): array {
                $causer = $entry->causer;
                $properties = $entry->properties;
                $old = (array) ($properties->get('old') ?? []);
                $new = (array) ($properties->get('attributes') ?? []);
                $context = (array) ($properties->get('context') ?? []);

                return [
                    'id' => $entry->id,
                    'event' => $entry->event,
                    'created_at' => EthiopianCalendar::present($entry->created_at?->setTimezone($timezone), $locale),
                    'created_time' => $entry->created_at?->setTimezone($timezone)->format('H:i:s'),
                    'actor' => $causer instanceof User ? ['name' => $causer->name, 'email' => $causer->email] : null,
                    'subject' => $this->subject($entry->subject, $locale),
                    'changes' => array_values(array_map(
                        fn (string $key): array => [
                            'key' => $key,
                            'label' => $this->label($key),
                            'old' => array_key_exists($key, $old) ? $this->value($key, $old[$key]) : null,
                            'new' => array_key_exists($key, $new) ? $this->value($key, $new[$key]) : null,
                            'had_old' => array_key_exists($key, $old),
                        ],
                        array_values(array_unique([...array_keys($old), ...array_keys($new)])),
                    )),
                    'context' => array_values(array_map(
                        fn (string $key): array => ['key' => $key, 'label' => $this->label($key), 'value' => $this->value($key, $context[$key])],
                        array_keys($context),
                    )),
                ];
            });

        return Inertia::render('admin/audit-log/index', [
            'entries' => Paginated::present($entries),
            'eventGroups' => $this->eventGroups(),
            'filters' => [
                'event' => $event?->value,
                'actor' => $actor,
                'subject' => $subject,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * The events, by area (the part before the dot), in the order of the enum.
     *
     * @return array<string, list<string>>
     */
    private function eventGroups(): array
    {
        $groups = [];

        foreach (AuditEvent::cases() as $event) {
            $area = Str::before($event->value, '.');
            $groups[$area === 'applicant' ? 'user' : $area][] = $event->value;
        }

        return $groups;
    }

    /**
     * Entries about the permit, payment, person, branch or vehicle type the
     * text names: a permit number or one of its plates, a receipt number, a
     * name or e-mail, a branch code or name.
     */
    private function concerning(Builder $query, string $text): Builder
    {
        $like = "%{$text}%";
        $plate = '%'.preg_replace('/[\s-]+/u', '', $text).'%';

        $permits = Permit::query()
            ->whereLike('permit_number', $like)
            ->orWhereHas('vehicles', fn (Builder $vehicles) => $vehicles
                ->whereLike('plate_normalized', $like)
                ->orWhereRaw("REPLACE(REPLACE(plate_normalized, '-', ''), ' ', '') LIKE ?", [$plate]))
            ->select('id');

        return $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->where('subject_type', (new Permit)->getMorphClass())->whereIn('subject_id', $permits))
            ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Payment)->getMorphClass())
                ->whereIn('subject_id', Payment::query()->whereLike('receipt_number', $like)->orWhereIn('permit_id', $permits)->select('id')))
            ->orWhere(fn (Builder $query) => $query->where('subject_type', (new User)->getMorphClass())
                ->whereIn('subject_id', User::query()->whereLike('name', $like)->orWhereLike('email', $like)->select('id')))
            ->orWhere(fn (Builder $query) => $query->where('subject_type', (new Branch)->getMorphClass())
                ->whereIn('subject_id', Branch::query()->whereLike('code', $like)->orWhereLike('name_am', $like)->orWhereLike('name_en', $like)->select('id')))
            ->orWhere(fn (Builder $query) => $query->where('subject_type', (new VehicleType)->getMorphClass())
                ->whereIn('subject_id', VehicleType::query()->whereLike('code', $like)->orWhereLike('name_am', $like)->orWhereLike('name_en', $like)->select('id'))));
    }

    /**
     * The label of a recorded field: a setting's label in System settings,
     * else the audit log's own, else the key written out in words.
     */
    private function label(string $key): string
    {
        if (array_key_exists($key, SettingsRegistry::all())) {
            return __('admin.settings.fields.'.SettingsRegistry::field($key));
        }

        return Lang::has("admin.audit.fields.{$key}")
            ? __("admin.audit.fields.{$key}")
            : Str::ucfirst(str_replace(['_', '.'], [' ', ' › '], $key));
    }

    /**
     * A recorded value as the reader understands it: statuses, sections,
     * languages and the like by name; lists joined; the rest as recorded.
     */
    private function value(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => (string) $this->value($key, $item), $value));
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        $translation = match ($key) {
            'status', 'stamp' => "permits.status.{$value}",
            'section' => "admin.settings.sections.{$value}",
            'roles' => "roles.{$value}.name",
            'language' => "admin.audit.values.language.{$value}",
            'purpose' => "admin.audit.values.purpose.{$value}",
            'reason' => "admin.audit.values.reason.{$value}",
            default => null,
        };

        return $translation !== null && Lang::has($translation) ? __($translation) : $value;
    }

    private function text(Request $request, string $name): string
    {
        $value = $request->query($name);

        return is_string($value) ? trim($value) : '';
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, (string) config('localization.display_timezone'));
        } catch (Throwable) {
            return null;
        }
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
            $subject instanceof Branch => ['type' => 'branch', 'label' => $subject->code.' · '.$subject->localizedName($locale), 'url' => route('admin.branches.edit', $subject)],
            $subject instanceof FeeSchedule => ['type' => 'fee_schedule', 'label' => '#'.$subject->id, 'url' => route('admin.fee-schedules.edit', $subject)],
            $subject instanceof Vehicle => ['type' => 'vehicle', 'label' => (string) $subject->plate_normalized, 'url' => null],
            $subject instanceof Role => ['type' => 'role', 'label' => $subject->label($locale), 'url' => route('admin.roles.edit', $subject)],
            default => null,
        };
    }
}
