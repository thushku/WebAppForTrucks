{{-- The truck's trips in the period (M39). --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $money = auth()->user()->can('trip.freight_view');
@endphp
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-end justify-between gap-3 px-6 pt-5">
    <div>
      <h3 class="font-semibold">{{ __('Trips of this truck') }}</h3>
      <p class="text-sm text-ink-500">{{ trans_choice(':count trip|:count trips', $activity->count()) }} · {{ number_format($activity->sum('km')) }} km @if ($money) · {{ __('freight :amount', ['amount' => $m($activity->where('trip.status', '!=', 'cancelled')->sum('freight'))]) }}@endif</p>
    </div>
    @include('trucks.partials._period')
  </div>
  <div class="relative mt-4 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Date') }}</th><th>{{ __('Route') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Km') }}</th>@if ($money)<th class="text-end">{{ __('Freight') }}</th>@endif</tr></thead>
      <tbody>
        @forelse ($activity as $r)
          <tr>
            <td><a href="{{ route('trips.show', $r->trip) }}" class="docno whitespace-nowrap font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $r->trip->trip_number ?? __('Draft trip') }}</a></td>
            <td class="whitespace-nowrap text-sm">{{ $r->trip->trip_date?->format('d M Y') }}</td>
            <td class="text-sm">{{ $r->route ?: '—' }}</td>
            <td class="text-sm">{{ $r->driver ?? '—' }}</td>
            <td>@include('trips._status', ['status' => $r->trip->status])</td>
            <td class="text-end text-sm">{{ $r->km ? number_format($r->km) : '—' }}</td>
            @if ($money)<td class="money text-end text-sm">{{ $m($r->freight) }}</td>@endif
          </tr>
        @empty
          <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ __('No trip in this period.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
