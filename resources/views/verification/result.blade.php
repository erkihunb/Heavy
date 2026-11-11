@extends('verification.layout')

@php
    $result = $verification['result'];
    $permit = $verification['permit'];
    $tones = [
        'valid' => 'border-success bg-success text-success-foreground',
        'not_yet_valid' => 'border-highlight bg-highlight text-highlight-foreground',
        'expired' => 'border-border bg-muted text-foreground',
        'revoked' => 'border-destructive bg-destructive text-white',
        'not_found' => 'border-destructive bg-card text-foreground',
    ];
    $symbols = ['valid' => '✓', 'not_yet_valid' => '…', 'expired' => '—', 'revoked' => '✕', 'not_found' => '?'];
@endphp

@section('title', __("verification.results.{$result}.title"))

@section('content')
    <section aria-labelledby="result-title"
             class="rounded-panel border-2 p-6 text-center sm:p-8 {{ $tones[$result] }}"
             data-result="{{ $result }}">
        <p class="text-6xl font-black leading-none" aria-hidden="true">{{ $symbols[$result] }}</p>
        <h1 id="result-title" class="mt-3 text-4xl font-black tracking-wide uppercase sm:text-5xl">
            {{ __("verification.results.{$result}.title") }}
        </h1>
        <p class="mt-3 text-lg font-medium">{{ __("verification.results.{$result}.body") }}</p>
        <p class="mt-4 text-sm opacity-80">
            {{ __('verification.checked_at', ['date' => $verification['checked_at']['ethiopian_label'], 'time' => $verification['checked_time']]) }}
        </p>
    </section>

    @if ($permit)
        <section aria-labelledby="details-title" class="mt-6 rounded-card border border-border bg-card p-6 sm:p-8">
            <h2 id="details-title" class="sr-only">{{ __('verification.details') }}</h2>
            <dl class="divide-y divide-border">
                <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                    <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.permit_number') }}</dt>
                    <dd class="text-xl font-extrabold tracking-wide tabular-nums" translate="no">{{ $permit['permit_number'] }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                    <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.permit_type') }}</dt>
                    <dd class="font-bold">{{ __("permits.types.{$permit['permit_type']}") }}</dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                    <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.vehicles') }}</dt>
                    <dd>
                        <ul class="space-y-2">
                            @foreach ($permit['vehicles'] as $vehicle)
                                <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="inline-flex rounded-control border border-border px-3 py-1 text-lg font-extrabold tracking-[0.08em] whitespace-nowrap tabular-nums" translate="no">{{ $vehicle['plate'] }}</span>
                                    <span class="text-sm text-muted-foreground">{{ __("permits.vehicle_roles.{$vehicle['role']}") }} · {{ $vehicle['vehicle_type'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </dd>
                </div>
                @if ($permit['organization'])
                    <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                        <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.organization') }}</dt>
                        <dd class="font-medium">{{ $permit['organization'] }}</dd>
                    </div>
                @endif
                <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                    <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.validity') }}</dt>
                    <dd>
                        <p class="font-bold">
                            <time datetime="{{ $permit['valid_from']['gregorian'] }}">{{ $permit['valid_from']['ethiopian_label'] }}</time>
                            –
                            <time datetime="{{ $permit['valid_to']['gregorian'] }}">{{ $permit['valid_to']['ethiopian_label'] }}</time>
                        </p>
                        <p class="text-sm text-muted-foreground tabular-nums">{{ $permit['valid_from']['gregorian'] }} – {{ $permit['valid_to']['gregorian'] }}</p>
                    </dd>
                </div>
                @if ($permit['time_window_rules'])
                    <div class="grid gap-1 py-3 sm:grid-cols-[11rem_1fr]">
                        <dt class="text-eyebrow text-muted-foreground">{{ __('verification.fields.rules') }}</dt>
                        <dd class="leading-relaxed whitespace-pre-line">{{ $permit['time_window_rules'] }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    <p class="mt-6 text-center">
        <a href="{{ route('verification.form') }}" class="font-semibold underline underline-offset-4">{{ __('verification.check_another') }}</a>
    </p>
@endsection
