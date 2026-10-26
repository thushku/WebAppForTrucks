@extends('layouts.app')
@section('title', __('Reports'))
@use('App\Http\Controllers\Reports\ReportController')
@use('App\Modules\Road\Services\EventTypes')
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $keep = ['tab' => $tab];
  $csv = auth()->user()->canAny(['report.export', 'statement.export']) ? route('reports.index', ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => 'csv']) : null;
@endphp
<div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Reports') }}">
  @foreach (ReportController::TABS as $key => $label)
    <a href="{{ route('reports.index', ['tab' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
       class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }}</a>
  @endforeach
</div>
@include('accounts._period', ['keep' => $keep, 'csv' => $csv])

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
  @switch($tab)
    @case('spending')
      <p class="px-6 pt-5 text-sm text-ink-500">{{ __('Approved crew entries in the period, by what and by who paid.') }}</p>
      <table class="table mt-2">
        <thead><tr><th>{{ __('Category') }}</th><th class="text-end">{{ __('Entries') }}</th>@foreach ($data['modes'] as $mode)<th class="text-end">{{ __(EventTypes::PAYMENT_MODES[$mode][0]) }}</th>@endforeach<th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>
          @forelse ($data['rows'] as $r)
            <tr><td class="text-sm font-medium">{{ $r['category'] }}</td><td class="text-end text-sm">{{ $r['count'] }}</td>@foreach ($data['modes'] as $mode)<td class="money text-end text-sm">{{ $r[$mode] ? $m($r[$mode]) : '—' }}</td>@endforeach<td class="money text-end text-sm font-semibold">{{ $m($r['total']) }}</td></tr>
          @empty
            <tr><td colspan="9" class="py-10 text-center text-ink-500">{{ __('No approved spending in this period.') }}</td></tr>
          @endforelse
        </tbody>
        @if ($data['rows']->isNotEmpty())<tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td><td class="text-end">{{ $data['rows']->sum('count') }}</td>@foreach ($data['modes'] as $mode)<td class="money text-end">{{ $m($data['rows']->sum($mode)) }}</td>@endforeach<td class="money text-end">{{ $m($data['total']) }}</td></tr></tfoot>@endif
      </table>
      @break

    @case('lanes')
      <p class="px-6 pt-5 text-sm text-ink-500">{{ __('Trips that ended in the period, by route: what a trip on it usually earns, costs and makes — what to quote the next client.') }} {{ __('“After truck costs” also takes off the trucks’ costs a day for the trip’s days and their tyres’ wear for its km (an estimate).') }}</p>
      <table class="table mt-2">
        <thead><tr><th>{{ __('Route') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Income per trip') }}</th><th class="text-end">{{ __('Cost per trip') }}</th><th class="text-end">{{ __('Profit per trip') }}</th><th class="text-end">{{ __('Margin') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Per km') }}</th><th class="text-end">{{ __('Days') }}</th><th>{{ __('Main costs per trip') }}</th><th>{{ __('Last trip') }}</th></tr></thead>
        <tbody>
          @forelse ($data as $r)
            <tr>
              <td class="text-sm font-medium">{{ $r->route ?: '—' }}@if ($r->trips > 1)<div class="text-xs text-ink-500">{{ __('Best :best · worst :worst', ['best' => $m($r->best), 'worst' => $m($r->worst)]) }}</div>@endif</td>
              <td class="text-end text-sm">{{ $r->trips }}</td>
              <td class="money text-end text-sm">{{ $m($r->income) }}</td>
              <td class="money text-end text-sm">{{ $m($r->cost) }}</td>
              <td class="money text-end text-sm font-semibold {{ $r->profit < 0 ? 'text-overdue' : '' }}">{{ $m($r->profit) }}@if ($r->fixed + $r->tyres > 0)<div class="whitespace-nowrap text-xs font-normal {{ $r->net < 0 ? 'text-overdue' : 'text-ink-500' }}" title="{{ __('Per trip: fixed costs :fixed, tyre wear :tyres', ['fixed' => $m($r->fixed), 'tyres' => $m($r->tyres)]) }}">{{ __('after truck costs :amount', ['amount' => $m($r->net)]) }}</div>@endif</td>
              <td class="text-end text-sm">{{ $r->margin !== null ? $r->margin . '%' : '—' }}</td>
              <td class="text-end text-sm">{{ $r->km ? number_format($r->km) : '—' }}</td>
              <td class="money text-end text-sm">{{ $r->per_km !== null ? $m($r->per_km) : '—' }}</td>
              <td class="text-end text-sm">{{ $r->days ?? '—' }}</td>
              <td class="text-xs text-ink-600">@forelse ($r->main_costs as $what => $amount)<div class="whitespace-nowrap">{{ $what }} <span class="money">{{ $m($amount) }}</span></div>@empty — @endforelse</td>
              <td class="whitespace-nowrap text-xs"><a href="{{ route('trips.show', $r->last['trip']) }}" class="font-mono hover:text-brand-700">{{ $r->last['trip']->trip_number }}</a><div class="text-ink-500">{{ $r->last['date']?->format('d M') }} · {{ $m($r->last['income']) }}</div></td>
            </tr>
          @empty
            <tr><td colspan="11" class="py-10 text-center text-ink-500">{{ __('No trip ended in this period.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
      @break

    @case('no_receipt')
      <div class="grid gap-6 p-6 lg:grid-cols-2">
        <div>
          <h3 class="font-semibold">{{ __('By person') }}</h3>
          <table class="table mt-2">
            <thead><tr><th>{{ __('Person') }}</th><th class="text-end">{{ __('Entries') }}</th><th class="text-end">{{ __('Approved') }}</th><th class="text-end">{{ __('Rejected') }}</th><th class="text-end">{{ __('All entered') }}</th></tr></thead>
            <tbody>@forelse ($data['by_person'] as $r)<tr><td class="text-sm">{{ $r['name'] }}</td><td class="text-end text-sm">{{ $r['count'] }}</td><td class="money text-end text-sm">{{ $m($r['approved']) }}</td><td class="money text-end text-sm text-ink-500">{{ $m($r['rejected']) }}</td><td class="money text-end text-sm font-medium">{{ $m($r['total']) }}</td></tr>@empty<tr><td colspan="5" class="py-6 text-center text-sm text-ink-500">{{ __('None in this period.') }}</td></tr>@endforelse</tbody>
          </table>
        </div>
        <div>
          <h3 class="font-semibold">{{ __('By route') }}</h3>
          <table class="table mt-2">
            <thead><tr><th>{{ __('Route') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Entries') }}</th><th class="text-end">{{ __('Approved') }}</th><th class="text-end">{{ __('All entered') }}</th></tr></thead>
            <tbody>@forelse ($data['by_route'] as $r)<tr><td class="text-sm">{{ $r['route'] }}</td><td class="text-end text-sm">{{ $r['trips'] }}</td><td class="text-end text-sm">{{ $r['count'] }}</td><td class="money text-end text-sm">{{ $m($r['approved']) }}</td><td class="money text-end text-sm font-medium">{{ $m($r['total']) }}</td></tr>@empty<tr><td colspan="5" class="py-6 text-center text-sm text-ink-500">{{ __('None in this period.') }}</td></tr>@endforelse</tbody>
          </table>
        </div>
      </div>
      @if ($data['events']->isNotEmpty())
        <table class="table">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Person') }}</th><th>{{ __('Trip') }}</th><th>{{ __('Paid to') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
          <tbody>@foreach ($data['events'] as $e)<tr class="{{ $e->status === 'rejected' ? 'opacity-60' : '' }}"><td class="whitespace-nowrap text-sm">{{ $e->event_at?->format('d M, H:i') }}</td><td class="text-sm">{{ $e->person?->name }}</td><td><a href="{{ route('trips.show', $e->trip_id) }}" class="font-mono text-xs hover:text-brand-700">{{ $e->trip?->trip_number }}</a></td>
            <td class="text-sm">{{ __(ucfirst($e->data['authority'] ?? 'other')) }}</td><td class="text-sm">{{ $e->data['reason'] ?? '' }}@if (! empty($e->data['place']))<div class="text-xs text-ink-500">{{ $e->data['place'] }}</div>@endif</td><td class="money text-end text-sm">{{ $m($e->amount) }}</td><td class="text-xs">{{ __(\App\Modules\Road\Models\RoadEvent::STATUSES[$e->status] ?? $e->status) }}</td></tr>@endforeach</tbody>
          <tfoot><tr class="font-semibold"><td colspan="5">{{ __('Total (approved :a)', ['a' => $m($data['approved'])]) }}</td><td class="money text-end">{{ $m($data['total']) }}</td><td></td></tr></tfoot>
        </table>
      @endif
      @break

    @case('fuel')
      <p class="px-6 pt-5 text-sm text-ink-500">{{ __('Fuel entered on trips in the period against the km of trips that ended; a truck below 95% of its standard km per litre is marked.') }}</p>
      <table class="table mt-2">
        <thead><tr><th>{{ __('Truck') }}</th><th class="text-end">{{ __('Fills') }}</th><th class="text-end">{{ __('Litres') }}</th><th class="text-end">{{ __('Fuel cost') }}</th><th class="text-end">{{ __('Trips · km') }}</th><th class="text-end">{{ __('Km per litre') }}</th><th class="text-end">{{ __('Standard') }}</th><th class="text-end">{{ __('Fuel per km') }}</th></tr></thead>
        <tbody>
          @forelse ($data as $r)
            <tr><td><a href="{{ route('trucks.show', $r->truck) }}" class="font-mono text-sm font-semibold hover:text-brand-700">{{ $r->truck->reg_no }}</a></td><td class="text-end text-sm">{{ $r->fills }}</td><td class="text-end text-sm">{{ number_format($r->litres, 1) }}</td><td class="money text-end text-sm">{{ $m($r->cost) }}</td>
              <td class="text-end text-sm">{{ $r->trips }} · {{ number_format($r->km) }}</td>
              <td class="text-end text-sm font-semibold {{ $r->short ? 'text-overdue' : '' }}">{{ $r->kmpl ?? '—' }}@if ($r->short)<span class="sr-only"> {{ __('below standard') }}</span> <x-icon name="alert" class="inline size-4" />@endif</td>
              <td class="text-end text-sm text-ink-500">{{ $r->standard ?? '—' }}</td><td class="money text-end text-sm">{{ $r->per_km ? $m($r->per_km) : '—' }}</td></tr>
          @empty
            <tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('No fuel entries or finished trips in this period.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
      @break

    @case('drivers')
      <table class="table">
        <thead><tr><th>{{ __('Driver') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Km driven') }}</th><th class="text-end">{{ __('Hours at the wheel') }}</th><th class="text-end">{{ __('Money entries') }}</th><th class="text-end">{{ __('Rejected') }}</th><th class="text-end">{{ __('Without receipt') }}</th></tr></thead>
        <tbody>
          @forelse ($data as $r)
            <tr><td class="text-sm font-medium">@if ($r->person)<a href="{{ route('people.show', $r->person) }}" class="hover:text-brand-700">{{ $r->person->name }}</a>@endif</td><td class="text-end text-sm">{{ $r->trips }}</td><td class="text-end text-sm">{{ number_format($r->km) }}</td>
              <td class="text-end text-sm">{{ intdiv($r->minutes, 60) }} h {{ $r->minutes % 60 }} min</td><td class="text-end text-sm">{{ $r->entries }}</td><td class="text-end text-sm {{ $r->rejected ? 'text-overdue' : '' }}">{{ $r->rejected }}</td><td class="money text-end text-sm">{{ $m($r->without_receipt) }}</td></tr>
          @empty
            <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ __('No driving in this period.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
      @break

    @default
      <p class="px-6 pt-5 text-sm text-ink-500">{{ __('Trips that ended in the period. Income and cost come from the books: bills, road spending, hire, bata, office entries.') }}</p>
      <table class="table mt-2">
        <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Route') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Profit') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Per km') }}</th><th class="text-end">{{ __('Margin') }}</th></tr></thead>
        <tbody>
          @forelse ($data as $r)
            <tr><td><a href="{{ route('trips.show', $r->trip) }}" class="font-mono text-xs font-semibold hover:text-brand-700">{{ $r->trip->trip_number }}</a><div class="text-xs text-ink-500">{{ $r->trip->vehicleLabel() }} · {{ $r->trip->ended_at?->format('d M') }}</div></td>
              <td class="text-sm">{{ $r->route }}</td><td class="money text-end text-sm">{{ $m($r->income) }}</td><td class="money text-end text-sm">{{ $m($r->cost) }}</td>
              <td class="money text-end text-sm font-semibold {{ $r->profit < 0 ? 'text-overdue' : '' }}">{{ $m($r->profit) }}</td><td class="text-end text-sm">{{ $r->km ? number_format($r->km) : '—' }}</td>
              <td class="money text-end text-sm">{{ $r->per_km !== null ? $m($r->per_km) : '—' }}</td><td class="text-end text-sm">{{ $r->margin !== null ? $r->margin . '%' : '—' }}</td></tr>
          @empty
            <tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('No trip ended in this period.') }}</td></tr>
          @endforelse
        </tbody>
        @if ($data->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="2">{{ trans_choice(':count trip|:count trips', $data->count()) }}</td><td class="money text-end">{{ $m($data->sum('income')) }}</td><td class="money text-end">{{ $m($data->sum('cost')) }}</td><td class="money text-end">{{ $m($data->sum('profit')) }}</td><td class="text-end">{{ number_format($data->sum('km')) }}</td><td></td><td></td></tr></tfoot>@endif
      </table>
  @endswitch
  </div>
</section>
@endsection
