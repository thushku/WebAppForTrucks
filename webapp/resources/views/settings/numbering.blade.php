@extends('layouts.settings')
@section('title', __('Numbering'))
@section('settings')
<section class="card p-6">
  <h2 class="text-xl font-semibold">{{ __('Document numbers') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('A number is taken only when a document is issued, never reused, and a cancelled document keeps its number.') }}</p>
  <details class="mt-4 rounded-xl bg-ink-50 p-4 text-sm">
    <summary class="cursor-pointer font-semibold">{{ __('Tokens you can use') }}</summary>
    <dl class="mt-3 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
      @foreach ([
        '{PREFIX}' => __('the prefix'), '{FY}' => __('financial year, 2026-27'), '{FY2}' => __('financial year, 26-27'),
        '{YYYY} {YY}' => __('year, 2026 / 26'), '{MM} {DD}' => __('month and day'), '{BR}' => __('branch code'),
        '{SHP} {FRM} {TO}' => __('shipper, from and to codes (trips)'), '{0001}' => __('the counter; its width sets the zeros'),
      ] as $token => $meaning)
        <div class="flex gap-2"><dt class="docno font-mono text-xs font-semibold text-brand-700">{{ $token }}</dt><dd class="text-ink-700">{{ $meaning }}</dd></div>
      @endforeach
    </dl>
  </details>
</section>

@foreach ($sequences as $sequence)
  <form method="POST" action="{{ route('settings.numbering.update', $sequence) }}" class="card p-6" data-numbering data-preview-url="{{ route('settings.numbering.preview', $sequence) }}" novalidate>
    @csrf @method('PUT')
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-lg font-semibold">{{ $sequence->displayName() }}
        @if ($sequence->max_length)<span class="pill ms-1 bg-amber-50 text-partial">{{ __('max :n characters', ['n' => $sequence->max_length]) }}</span>@endif
      </h3>
      <div class="text-sm">{{ __('Next') }}: <output class="docno rounded-lg bg-brand-50 px-2.5 py-1 font-mono font-semibold text-brand-800" data-preview>{{ $previews[$sequence->id] }}</output></div>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
      <div>
        <label class="label" for="pre_{{ $sequence->id }}">{{ __('Prefix') }}</label>
        <input id="pre_{{ $sequence->id }}" name="prefix" class="input font-mono" value="{{ $sequence->prefix }}" maxlength="20">
      </div>
      <div>
        <label class="label" for="fmt_{{ $sequence->id }}">{{ __('Format') }}</label>
        <input id="fmt_{{ $sequence->id }}" name="format" class="input font-mono text-sm" value="{{ $sequence->format }}" maxlength="60" required>
      </div>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_7rem_7rem]">
      <div>
        <label class="label" for="rst_{{ $sequence->id }}">{{ __('Start again') }}</label>
        <select id="rst_{{ $sequence->id }}" name="reset_policy" class="input">
          @foreach (\App\Modules\Settings\Models\DocumentSequence::RESET_POLICIES as $value => $label)
            <option value="{{ $value }}" @selected($sequence->reset_policy === $value)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="label whitespace-nowrap" for="nxt_{{ $sequence->id }}">{{ __('Next no.') }}</label>
        <input id="nxt_{{ $sequence->id }}" name="next_number" type="number" min="1" class="input" value="{{ $nexts[$sequence->id] }}" title="{{ __('For period :period', ['period' => $periods[$sequence->id]]) }}">
      </div>
      <div>
        <label class="label whitespace-nowrap" for="st_{{ $sequence->id }}">{{ __('Starts at') }}</label>
        <input id="st_{{ $sequence->id }}" name="start_number" type="number" min="1" class="input" value="{{ $sequence->start_number }}" title="{{ __('Where each new period starts') }}">
      </div>
    </div>
    <p class="error" data-preview-error @if (! $errors->any()) hidden @endif>{{ $errors->first() }}</p>
    <div class="mt-4 flex items-center justify-between gap-3">
      <p class="text-xs text-ink-500">{{ __('“Next no.” is for the current period (:period).', ['period' => $periods[$sequence->id]]) }}</p>
      <button type="submit" class="btn-secondary"><x-icon name="check" /> {{ __('Save') }}</button>
    </div>
  </form>
@endforeach
@endsection
