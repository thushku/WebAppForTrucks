@extends('layouts.app')
@section('title', __('Truck profit'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
@include('accounts._period', ['keep' => array_filter(['truck' => $truck?->id])])

<section class="card overflow-hidden">
  <h2 class="px-6 pt-6 text-lg font-semibold">{{ __('Profit per truck') }}</h2>
  <p class="px-6 text-sm text-ink-500">{{ __('From the book: freight and charges billed for the truck’s trips, less its running costs (fuel, tolls, bata, hire…) and maintenance.') }}</p>
  <div class="overflow-x-auto">
    <table class="table mt-3">
      <thead><tr><th>{{ __('Truck') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Running costs') }}</th><th class="text-end">{{ __('Maintenance') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
      <tbody>
        @forelse ($rows->sortByDesc('profit') as $r)
          <tr class="{{ $truck?->id === $r->truck_id ? 'bg-brand-50/60' : '' }}">
            <td><a href="{{ route('accounts.trucks', ['truck' => $r->truck_id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="font-mono text-sm font-semibold hover:text-brand-700">{{ $trucks[$r->truck_id]?->reg_no ?? '#' . $r->truck_id }}</a></td>
            <td class="text-end text-sm">{{ $r->trips }}</td>
            <td class="money text-end text-sm">{{ $m($r->income) }}</td><td class="money text-end text-sm">{{ $m($r->running) }}</td><td class="money text-end text-sm">{{ $m($r->maintenance) }}</td>
            <td class="money text-end text-sm font-semibold {{ $r->profit < 0 ? 'text-overdue' : '' }}">{{ $m($r->profit) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ __('No truck costs or income in this period yet.') }}</td></tr>
        @endforelse
      </tbody>
      @if ($rows->isNotEmpty())
        <tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td><td class="text-end">{{ $rows->sum('trips') }}</td><td class="money text-end">{{ $m($rows->sum('income')) }}</td><td class="money text-end">{{ $m($rows->sum('running')) }}</td><td class="money text-end">{{ $m($rows->sum('maintenance')) }}</td><td class="money text-end">{{ $m($rows->sum('profit')) }}</td></tr></tfoot>
      @endif
    </table>
  </div>
</section>

@if ($truck)
  <section class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-6">
      <h2 class="text-lg font-semibold">{{ __(':truck month by month', ['truck' => $truck->reg_no]) }}</h2>
      <a href="{{ route('accounts.profit-loss', ['truck_id' => $truck->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-sm text-brand-700 hover:underline">{{ __('Profit and loss of this truck') }}</a>
    </div>
    <table class="table mt-3">
      <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Running costs') }}</th><th class="text-end">{{ __('Maintenance') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
      <tbody>
        @foreach ($months as $row)
          <tr><td class="text-sm">{{ \Carbon\CarbonImmutable::parse($row->month . '-01')->translatedFormat('M Y') }}</td><td class="money text-end text-sm">{{ $m($row->income) }}</td><td class="money text-end text-sm">{{ $m($row->running) }}</td><td class="money text-end text-sm">{{ $m($row->maintenance) }}</td><td class="money text-end text-sm font-semibold {{ $row->profit < 0 ? 'text-overdue' : '' }}">{{ $m($row->profit) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endif
@endsection
