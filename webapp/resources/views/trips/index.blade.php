@extends('layouts.app')
@section('title', __('Trips'))
@section('content')
@php
  $f = $filters;
  $tabs = ['active' => __('Open'), 'draft' => __('Draft'), 'assigned' => __('Sent'), 'running' => __('Running'), 'ended' => __('Ended'), 'closed' => __('Closed'), 'cancelled' => __('Cancelled'), 'all' => __('All')];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Status') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('trips.index', array_filter(['status' => $key] + collect($f)->except('status')->all())) }}" role="tab" aria-selected="{{ $f['status'] === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $f['status'] === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">
        {{ $label }}@if (isset($counts[$key]))<span class="ms-1 text-xs text-ink-400">{{ $counts[$key] }}</span>@endif
      </a>
    @endforeach
  </div>
  @can('trip.create')
    <div class="flex gap-2">
      <a href="{{ route('trips.create', ['load' => 'ptl']) }}" class="btn-secondary">{{ __('Part-load trip') }}</a>
      <a href="{{ route('trips.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('New trip') }}</a>
    </div>
  @endcan
</div>

<form method="GET" action="{{ route('trips.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="status" value="{{ $f['status'] }}">
  <div><label class="label" for="t_from">{{ __('From date') }}</label><input id="t_from" type="date" name="from" value="{{ $f['from'] ?? '' }}" class="input"></div>
  <div><label class="label" for="t_to">{{ __('To date') }}</label><input id="t_to" type="date" name="to" value="{{ $f['to'] ?? '' }}" class="input"></div>
  <div><label class="label" for="t_place">{{ __('Place') }}</label><select id="t_place" name="place" class="input w-40"><option value="">{{ __('Any') }}</option>@foreach ($places as $id => $name)<option value="{{ $id }}" @selected((int) ($f['place'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></div>
  <div><label class="label" for="t_truck">{{ __('Truck') }}</label><select id="t_truck" name="truck" class="input w-40"><option value="">{{ __('Any') }}</option>@foreach ($trucks as $id => $reg)<option value="{{ $id }}" @selected((int) ($f['truck'] ?? 0) === $id)>{{ $reg }}</option>@endforeach</select></div>
  <div><label class="label" for="t_party">{{ __('Client') }}</label><select id="t_party" name="party" class="input w-44"><option value="">{{ __('Any') }}</option>@foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((int) ($f['party'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></div>
  <div><label class="label" for="t_q">{{ __('Trip no.') }}</label><input id="t_q" name="q" value="{{ $f['q'] ?? '' }}" class="input w-40" maxlength="40"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <a href="{{ route('trips.index') }}" class="btn-ghost text-sm">{{ __('Reset filters') }}</a>
</form>

{{-- Totals that follow the filters (13 §A #4). --}}
<div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-2xl bg-brand-50 px-5 py-3 text-sm text-brand-900">
  <span><strong>{{ $totals['count'] }}</strong> {{ trans_choice('trip|trips', $totals['count']) }}</span>
  @can('trip.freight_view')
    <span>{{ __('Freight') }} <strong class="money">{{ \App\Support\Money::company($totals['freight']) }}</strong></span>
    <span>{{ __('Other charges') }} <strong class="money">{{ \App\Support\Money::company($totals['other']) }}</strong></span>
    <span>{{ __('Total') }} <strong class="money">{{ \App\Support\Money::company($totals['freight'] + $totals['other']) }}</strong></span>
  @endcan
</div>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Route') }}</th><th>{{ __('Truck') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Client') }}</th>@can('trip.freight_view')<th class="text-end">{{ __('Freight') }}</th>@endcan<th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @forelse ($trips as $t)
          @php($loads = $t->consignments->where('status', '!=', 'cancelled'))
          <tr class="{{ $t->status === 'cancelled' ? 'opacity-50' : '' }}">
            <td>
              <a href="{{ route('trips.show', $t) }}" class="docno font-mono text-sm font-semibold text-ink-900 hover:text-brand-700">{{ $t->trip_number ?? __('Draft') }}</a>
              <div class="text-xs text-ink-500">{{ $t->trip_date->format('d M Y') }} · {{ __(\App\Modules\Trips\Models\Trip::LOAD_TYPES[$t->load_type]) }}</div>
            </td>
            <td class="text-sm">{{ $t->from->name }} → {{ $t->to->name }}</td>
            <td class="docno font-mono text-xs">{{ $t->vehicleLabel() }}</td>
            <td class="text-sm">{{ $t->crew->firstWhere('role', 'driver')?->person->name ?? '—' }}</td>
            <td class="text-sm">{{ $loads->pluck('billingParty.name')->unique()->join(', ') ?: '—' }}</td>
            @can('trip.freight_view')<td class="money text-end">{{ \App\Support\Money::company($loads->sum('freight') + (float) $t->other_charges) }}</td>@endcan
            <td>@include('trips._status', ['status' => $t->status])</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-12 text-center text-ink-500">{{ __('No trips match. Plan your first trip.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($trips->hasPages())<div class="p-4">{{ $trips->links() }}</div>@endif
</section>
@endsection
