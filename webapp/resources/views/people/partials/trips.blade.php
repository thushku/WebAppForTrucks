{{-- The trips a person was crew on in the period (M41). --}}
@php($m = fn ($v) => \App\Support\Money::company($v))
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-end justify-between gap-3 px-6 pt-5">
    <div>
      <h3 class="font-semibold">{{ __('Trips of :name', ['name' => $person->name]) }}</h3>
      <p class="text-sm text-ink-500">{{ trans_choice(':count trip|:count trips', $activity->count()) }} · {{ number_format($activity->sum('km')) }} km · {{ __('spent :amount', ['amount' => $m($activity->sum('spent'))]) }}</p>
    </div>
    @include('people.partials._period')
  </div>
  <div class="relative mt-4 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Date') }}</th><th>{{ __('Route') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Spent') }}</th></tr></thead>
      <tbody>
        @forelse ($activity as $r)
          <tr>
            <td><a href="{{ route('trips.show', $r->trip) }}" class="docno whitespace-nowrap font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $r->trip->trip_number ?? __('Draft trip') }}</a></td>
            <td class="whitespace-nowrap text-sm">{{ $r->trip->trip_date?->format('d M Y') }}</td>
            <td class="text-sm">{{ $r->route ?: '—' }}</td>
            <td class="whitespace-nowrap font-mono text-sm">{{ $r->vehicle ?? '—' }}</td>
            <td class="text-sm">{{ __(\App\Modules\Trips\Models\TripCrew::ROLES[$r->role] ?? (string) $r->role) }}</td>
            <td>@include('trips._status', ['status' => $r->trip->status])</td>
            <td class="text-end text-sm">{{ $r->km ? number_format($r->km) : '—' }}</td>
            <td class="money text-end text-sm">{{ $r->entries ? $m($r->spent) : '—' }}@if ($r->entries)<div class="text-xs text-ink-500">{{ trans_choice(':count entry|:count entries', $r->entries) }}</div>@endif</td>
          </tr>
        @empty
          <tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('No trip in this period.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
