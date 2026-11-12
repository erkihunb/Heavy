@extends('verification.layout')

@section('title', __('verification.lookup.title'))

@push('scripts')
    @vite(['resources/js/verify-scanner.ts'])
@endpush

@section('content')
    <h1 class="text-page-title">{{ __('verification.lookup.title') }}</h1>

    {{--
        Scanning the QR code is the main way in. The button is revealed by
        verify-scanner.ts; without JavaScript only the form below is shown.
    --}}
    <section class="mt-5"
             data-qr-scanner
             data-verify-base="{{ rtrim((string) config('permits.verify_url'), '/') }}"
             data-msg-starting="{{ __('verification.officer.scanner.starting') }}"
             data-msg-hint="{{ __('verification.officer.scanner.hint') }}"
             data-msg-denied="{{ __('verification.officer.scanner.denied') }}"
             data-msg-unavailable="{{ __('verification.officer.scanner.unavailable') }}"
             data-msg-not-permit="{{ __('verification.officer.scanner.not_permit') }}"
             data-msg-opening="{{ __('verification.lookup.opening') }}">
        <button type="button" data-scan-start hidden
                class="flex w-full flex-col items-center gap-3 rounded-panel border-2 border-primary bg-primary px-6 py-7 text-primary-foreground shadow-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
            <svg viewBox="0 0 24 24" class="size-14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 8V5.5A1.5 1.5 0 0 1 5.5 4H8M16 4h2.5A1.5 1.5 0 0 1 20 5.5V8M20 16v2.5a1.5 1.5 0 0 1-1.5 1.5H16M8 20H5.5A1.5 1.5 0 0 1 4 18.5V16"/>
                <rect x="7.5" y="7.5" width="3.5" height="3.5" rx=".5"/><rect x="13" y="7.5" width="3.5" height="3.5" rx=".5"/>
                <rect x="7.5" y="13" width="3.5" height="3.5" rx=".5"/><path d="M13 13h1.5v1.5H13zM15.5 15.5H17V17h-1.5zM13 16.5h1"/>
            </svg>
            <span class="text-xl font-extrabold">{{ __('verification.officer.scanner.start') }}</span>
            <span class="text-sm opacity-90">{{ __('verification.lookup.scan_intro') }}</span>
        </button>

        <div data-scan-panel hidden class="overflow-hidden rounded-panel border border-border bg-card shadow-sm">
            <div class="relative aspect-square w-full bg-black sm:aspect-video">
                <video muted playsinline aria-label="{{ __('verification.officer.scanner.camera') }}" class="size-full object-cover"></video>
                <div data-scan-frame hidden aria-hidden="true" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="size-3/5 max-h-72 max-w-72 rounded-card border-4 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                </div>
                <p data-scan-problem hidden role="alert"
                   class="absolute inset-0 flex items-center justify-center bg-card p-6 text-center text-sm font-medium leading-relaxed"></p>
            </div>
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <p data-scan-status aria-live="polite" class="text-sm text-muted-foreground"></p>
                <button type="button" data-scan-close
                        class="shrink-0 rounded-control px-3 py-1.5 text-sm font-semibold hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
                    {{ __('verification.officer.scanner.close') }}
                </button>
            </div>
        </div>
    </section>

    <div class="my-6 flex items-center gap-3 text-sm font-medium text-muted-foreground" aria-hidden="true">
        <span class="h-px flex-1 bg-border"></span>
        {{ __('verification.officer.scanner.or') }}
        <span class="h-px flex-1 bg-border"></span>
    </div>

    <p class="text-muted-foreground">{{ __('verification.lookup.intro') }}</p>

    <form method="POST" action="{{ route('verification.lookup') }}" novalidate
          class="mt-4 space-y-5 rounded-card border border-border bg-card p-6 sm:p-8">
        @csrf

        @foreach (['permit_number' => 'AACRA-2018-000123', 'plate' => 'አአ-03-A45301'] as $field => $example)
            <div class="flex flex-col gap-2">
                <label for="{{ $field }}" class="text-sm font-medium">{{ __("verification.fields.{$field}") }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field) }}"
                       autocomplete="off" autocapitalize="characters" spellcheck="false" translate="no"
                       placeholder="{{ $example }}" required
                       @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                       class="flex h-12 w-full rounded-control border border-input bg-transparent px-3 text-lg tabular-nums outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive">
                @error($field)
                    <p id="{{ $field }}-error" role="alert" class="text-sm text-destructive-foreground">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <button type="submit"
                class="inline-flex h-12 w-full items-center justify-center rounded-control bg-primary px-6 text-base font-semibold text-primary-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:w-auto">
            {{ __('verification.lookup.submit') }}
        </button>
    </form>
@endsection
