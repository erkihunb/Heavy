@extends('verification.layout')

@section('title', __('verification.lookup.title'))

@section('content')
    <h1 class="text-page-title">{{ __('verification.lookup.title') }}</h1>
    <p class="mt-2 text-muted-foreground">{{ __('verification.lookup.intro') }}</p>

    <form method="POST" action="{{ route('verification.lookup') }}" novalidate
          class="mt-6 space-y-5 rounded-card border border-border bg-card p-6 sm:p-8">
        @csrf

        @foreach (['permit_number' => 'TMA-HV-2018-000123', 'plate' => 'አአ-03-A45301'] as $field => $example)
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
