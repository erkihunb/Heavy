@extends('verification.layout')

@php
    use Carbon\CarbonImmutable;

    $result = $verification['result'];
    $permit = $verification['permit'];

    $tones = [
        'valid' => 'border-success bg-success text-success-foreground',
        'outside_hours' => 'border-warning bg-warning text-white',
        'not_yet_valid' => 'border-highlight bg-highlight text-highlight-foreground',
        'expired' => 'border-border bg-muted text-foreground',
        'revoked' => 'border-destructive bg-destructive text-white',
        'not_found' => 'border-destructive bg-card text-foreground',
    ];
    $iconTones = [
        'valid' => 'bg-white/20',
        'outside_hours' => 'bg-white/20',
        'not_yet_valid' => 'bg-black/10',
        'expired' => 'bg-foreground/10',
        'revoked' => 'bg-white/20',
        'not_found' => 'bg-destructive/10 text-destructive',
    ];

    // How the permit's period stands today: days left, days to wait, or when it ended.
    $timing = null;
    $progress = null;
    $daysLeft = null;

    if ($permit) {
        $zone = config('localization.display_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();
        $from = CarbonImmutable::parse($permit['valid_from']['gregorian'], $zone)->startOfDay();
        $to = CarbonImmutable::parse($permit['valid_to']['gregorian'], $zone)->startOfDay();

        if ($result === 'valid') {
            $left = (int) $today->diffInDays($to);
            $daysLeft = $left;
            $timing = $left === 0 ? __('verification.last_day') : trans_choice('verification.days_left', $left);
            $total = max(1, (int) $from->diffInDays($to) + 1);
            $progress = (int) round(min($total, (int) $from->diffInDays($today) + 1) / $total * 100);
        } elseif ($result === 'not_yet_valid') {
            $timing = trans_choice('verification.starts_in', max(1, (int) $today->diffInDays($from)));
        } elseif ($result === 'expired') {
            $timing = __('verification.ended_on', ['date' => $permit['valid_to']['ethiopian_label']]);
        }
    }
@endphp

@section('title', __("verification.results.{$result}.title"))

@section('content')
    {{-- The answer first: what an officer or a member of the public needs at a glance. --}}
    <section aria-labelledby="result-title"
             class="-mx-4 -mt-4 border-b-2 px-6 py-6 text-center sm:mx-0 sm:mt-0 sm:rounded-panel sm:border-2 sm:px-10 sm:py-8 sm:shadow-sm {{ $tones[$result] }}"
             data-result="{{ $result }}">
        <div class="mx-auto flex size-16 items-center justify-center rounded-full sm:size-20 {{ $iconTones[$result] }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-9 sm:size-11" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                @switch($result)
                    @case('valid')
                        <path d="M5 12.5l4.5 4.5L19 7.5"/>
                        @break
                    @case('not_yet_valid')
                        <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>
                        @break
                    @case('outside_hours')
                        <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2M4.5 4.5l15 15"/>
                        @break
                    @case('expired')
                        <path d="M7 3.5h10M7 20.5h10M8 3.5c0 4 8 4.5 8 8.5s-8 4.5-8 8.5M16 3.5c0 4-8 4.5-8 8.5"/>
                        @break
                    @case('revoked')
                        <path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>
                        @break
                    @default
                        <path d="M9.2 9a3 3 0 1 1 4.3 2.7c-.9.4-1.5 1.2-1.5 2.2V15M12 18.5v.01"/>
                @endswitch
            </svg>
        </div>

        <h1 id="result-title" class="mt-3 text-4xl font-black tracking-wide uppercase sm:text-5xl">
            {{ __("verification.results.{$result}.title") }}
        </h1>
        <p class="mx-auto mt-2 max-w-md text-lg font-medium">{{ __("verification.results.{$result}.body") }}</p>

        @if ($result === 'outside_hours' && $permit && $permit['hours'])
            {{-- Outside its hours: when it may move, and what time it is now. --}}
            <dl class="mx-auto mt-4 grid max-w-md gap-2 rounded-card bg-black/15 px-4 py-3 text-left text-base sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase opacity-80">{{ __('verification.hours.allowed') }}</dt>
                    <dd class="font-bold">{{ $permit['hours']['label'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase opacity-80">{{ __('verification.hours.now') }}</dt>
                    <dd class="font-bold">{{ $verification['checked_time_ethiopian'] }} ({{ $verification['checked_time'] }})</dd>
                </div>
            </dl>
        @endif

        @if ($permit)
            {{-- The period, right with the answer: from, until, and how much of it has passed. --}}
            <div class="mx-auto mt-5 max-w-lg rounded-card bg-black/15 px-5 py-4 text-left">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold opacity-90">{{ __('verification.fields.validity') }}</p>
                    {{-- Not started yet, or ended: said here; the days left of a valid permit get the centre. --}}
                    @if ($timing && $daysLeft === null)
                        <p class="text-sm font-bold">{{ $timing }}</p>
                    @endif
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs opacity-80">{{ __('verification.from') }}</p>
                        <p class="text-base font-extrabold sm:text-xl"><time datetime="{{ $permit['valid_from']['gregorian'] }}">{{ $permit['valid_from']['ethiopian_label'] }}</time></p>
                        <p class="text-xs whitespace-nowrap opacity-80 tabular-nums">{{ $permit['valid_from']['gregorian'] }}</p>
                    </div>
                    @if ($daysLeft !== null)
                        {{-- The days left, as large as the dates: what a check is usually about. --}}
                        <div class="flex shrink-0 flex-col items-center rounded-card bg-white/15 px-3 py-2 text-center sm:px-4" aria-label="{{ $timing }}">
                            @if ($daysLeft === 0)
                                <span class="text-sm font-extrabold leading-tight">{{ __('verification.last_day') }}</span>
                            @else
                                <span class="text-4xl font-black leading-none tabular-nums">{{ $daysLeft }}</span>
                                <span class="mt-1 text-xs font-bold">{{ trans_choice('verification.days_left_label', $daysLeft) }}</span>
                            @endif
                        </div>
                    @else
                        <span class="text-xl opacity-80" aria-hidden="true">→</span>
                    @endif
                    <div class="min-w-0 flex-1 text-right">
                        <p class="text-xs opacity-80">{{ __('verification.until') }}</p>
                        <p class="text-base font-extrabold sm:text-xl"><time datetime="{{ $permit['valid_to']['gregorian'] }}">{{ $permit['valid_to']['ethiopian_label'] }}</time></p>
                        <p class="text-xs whitespace-nowrap opacity-80 tabular-nums">{{ $permit['valid_to']['gregorian'] }}</p>
                    </div>
                </div>
                @if ($progress !== null)
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/20" role="img" aria-label="{{ $timing }}">
                        <div class="h-full rounded-full bg-white" style="width: {{ $progress }}%"></div>
                    </div>
                @endif
            </div>
        @endif
    </section>

    @if ($permit)
        @php
            // The permit's other details, in the order of the printed permit; empty ones are left out.
            $details = array_filter([
                'organization' => $permit['organization'],
                'reason' => $permit['reason'],
                'issued' => $permit['issued_at']
                    ? trim($permit['issued_at']['ethiopian_label'].' '.$permit['issued_time'])
                    : null,
                'branch' => $permit['branch'],
                'issuer' => $permit['issuer'],
                'receipt' => $permit['receipt_number'],
                'certificate' => $permit['certificate_code'],
            ], fn ($value) => filled($value));
        @endphp

        {{-- The permit itself, as one card: number, vehicles, period, hours and details. --}}
        <article aria-labelledby="details-title" class="mt-5 overflow-hidden rounded-panel border border-border bg-card shadow-sm">
            <h2 id="details-title" class="sr-only">{{ __('verification.details') }}</h2>

            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/50 px-5 py-4 sm:px-7">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-muted-foreground">{{ __('verification.fields.permit_number') }}</p>
                    <p class="text-xl font-extrabold tracking-wide tabular-nums sm:text-2xl" translate="no">{{ $permit['permit_number'] }}</p>
                </div>
                <span class="rounded-full border-2 border-primary px-4 py-1 text-sm font-bold text-primary">
                    {{ __("permits.types.{$permit['permit_type']}") }}
                </span>
            </header>

            {{-- The plates: what is matched against the vehicle at the roadside. --}}
            <section aria-labelledby="vehicles-title" class="px-5 py-6 text-center sm:px-7">
                <h3 id="vehicles-title" class="text-base font-bold">{{ __('verification.fields.plate_heading') }}</h3>
                <ul class="mt-4 flex flex-wrap justify-center gap-x-8 gap-y-4">
                    @foreach ($permit['vehicles'] as $vehicle)
                        <li>
                            <span class="inline-flex rounded-md border-[3px] border-foreground bg-white px-5 py-2 text-3xl font-black tracking-[0.1em] whitespace-nowrap text-black tabular-nums shadow-[inset_0_0_0_2px_white,inset_0_0_0_3px_black]" translate="no">{{ $vehicle['plate'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-sm text-muted-foreground">{{ __('verification.compare_plate') }}</p>
            </section>

            {{-- Each vehicle's type, role and owner, under its own heading. --}}
            <section aria-labelledby="vehicle-details-title" class="border-t border-border">
                <h3 id="vehicle-details-title" class="px-5 pt-5 text-sm font-medium text-muted-foreground sm:px-7">{{ __('verification.fields.vehicle_details') }}</h3>
                @foreach ($permit['vehicles'] as $vehicle)
                    @if (count($permit['vehicles']) > 1)
                        <p class="px-5 pt-3 text-sm font-extrabold tracking-wide tabular-nums sm:px-7" translate="no">{{ $vehicle['plate'] }}</p>
                    @endif
                    <dl class="grid sm:grid-cols-3">
                        <div class="px-5 py-3 sm:px-7">
                            <dt class="text-xs text-muted-foreground">{{ __('verification.fields.vehicle_type') }}</dt>
                            <dd class="font-bold">{{ $vehicle['vehicle_type'] }}</dd>
                        </div>
                        <div class="px-5 py-3 sm:px-7">
                            <dt class="text-xs text-muted-foreground">{{ __('verification.fields.vehicle_role') }}</dt>
                            <dd class="font-bold">{{ __("permits.vehicle_roles.{$vehicle['role']}") }}</dd>
                        </div>
                        @if ($vehicle['owner'])
                            <div class="px-5 py-3 sm:px-7">
                                <dt class="text-xs text-muted-foreground">{{ __('verification.fields.owner') }}</dt>
                                <dd class="font-bold break-words">{{ $vehicle['owner'] }}</dd>
                            </div>
                        @endif
                    </dl>
                @endforeach
                <div class="pb-2"></div>
            </section>

            {{-- The hours of the day it lets the vehicles move. --}}
            <section aria-labelledby="hours-title" class="border-t border-border px-5 py-5 sm:px-7">
                <h3 id="hours-title" class="text-sm font-medium text-muted-foreground">{{ __('verification.hours.title') }}</h3>
                <p class="mt-1 text-lg font-extrabold">
                    {{ $permit['hours'] ? $permit['hours']['label'] : __('permits.hours.any_time') }}
                </p>
                @if ($permit['hours'])
                    <p class="mt-1 text-sm text-muted-foreground">{{ __('verification.hours.note') }}</p>
                @endif
            </section>

            @if ($details !== [])
                <dl class="grid border-t border-border sm:grid-cols-2">
                    @foreach ($details as $field => $value)
                        <div class="border-b border-border px-5 py-4 last:border-b-0 sm:px-7 sm:odd:border-r">
                            <dt class="text-sm font-medium text-muted-foreground">{{ __("verification.fields.{$field}") }}</dt>
                            <dd class="mt-0.5 font-bold break-words @if (in_array($field, ['receipt', 'certificate'], true)) tabular-nums @endif @if ($field === 'certificate') tracking-[0.08em] text-lg @endif" @if (in_array($field, ['receipt', 'certificate'], true)) translate="no" @endif>{{ $value }}</dd>
                            @if ($field === 'certificate')
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ __('verification.fields.certificate_hint') }}</p>
                            @endif
                        </div>
                    @endforeach
                </dl>
            @endif
        </article>

        @if ($permit['time_window_rules'])
            <section aria-labelledby="rules-title" class="mt-5 rounded-card border-l-4 border-primary bg-primary/5 p-5 sm:p-6">
                <h2 id="rules-title" class="font-bold">{{ __('verification.fields.rules') }}</h2>
                <p class="mt-1.5 leading-relaxed whitespace-pre-line">{{ $permit['time_window_rules'] }}</p>
            </section>
        @endif
    @elseif ($result === 'not_found')
        <p class="mt-5 rounded-card border border-border bg-card p-5 text-center leading-relaxed sm:p-6">{{ __('verification.not_found_help') }}</p>
    @endif

    {{-- On a tablet or computer the next step sits at the end of the page… --}}
    <p class="mt-8 hidden text-center sm:block">
        <a href="{{ route('verification.form') }}"
           class="inline-flex min-h-12 items-center justify-center gap-2 rounded-control border-2 border-primary px-6 font-bold text-primary hover:bg-primary hover:text-primary-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
            {{ __('verification.check_another') }}
        </a>
    </p>

    {{-- …on a phone it is always at hand, in a bar fixed to the bottom; this keeps the page clear of it. --}}
    <div class="h-24 sm:hidden" aria-hidden="true"></div>
@endsection

@push('action-bar')
    <nav aria-label="{{ __('verification.check_another') }}"
         class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card/95 px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] backdrop-blur sm:hidden">
        <a href="{{ route('verification.form') }}"
           class="flex h-14 w-full items-center justify-center gap-2.5 rounded-control bg-primary text-base font-bold text-primary-foreground shadow-sm active:opacity-90 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16M7 12h10"/>
            </svg>
            {{ __('verification.check_another') }}
        </a>
    </nav>
@endpush
