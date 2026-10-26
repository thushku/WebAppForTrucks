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
  @include('settings._sequence')
@endforeach

@if ($branchSeries->isNotEmpty())
  <section class="card p-6">
    <h2 class="text-xl font-semibold">{{ __('Per branch') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('Each branch numbers these in its own run. A document takes its trip’s or its own branch; one with no branch uses the company-wide series above.') }}</p>
  </section>
  @foreach ($branchSeries as $sequence)
    @include('settings._sequence')
  @endforeach
@endif

@if ($gstSeries->isNotEmpty())
  <section class="card p-6">
    <h2 class="text-xl font-semibold">{{ __('Per GSTIN') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('GST wants each registration’s invoices in their own consecutive run. The main GSTIN (:gstin) uses the series above; each other GSTIN has its own below.', ['gstin' => $mainGstin]) }}</p>
  </section>
  @foreach ($gstSeries as $sequence)
    @include('settings._sequence')
  @endforeach
@endif
@endsection
