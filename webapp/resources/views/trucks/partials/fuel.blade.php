{{-- The truck's fuel in the period (M39): each fill, and km per litre between full tanks. --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $f = $activity;
  $short = $f['kmpl'] && $f['standard'] && $f['kmpl'] < $f['standard'] * 0.95;
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <h3 class="font-semibold">{{ __('Fuel of this truck') }}</h3>
    @include('trucks.partials._period')
  </div>
  <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 xl:grid-cols-5">
    <div><dt class="text-ink-500">{{ __('Litres') }}</dt><dd class="text-lg font-semibold">{{ number_format($f['litres'], 1) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Fuel cost') }}</dt><dd class="money text-lg font-semibold">{{ $m($f['cost']) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Km run on trips') }}</dt><dd class="text-lg font-semibold">{{ number_format($f['km']) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Km per litre') }}</dt><dd class="text-lg font-semibold {{ $short ? 'text-overdue' : '' }}">{{ $f['kmpl'] ?? '—' }}</dd>@if ($f['standard'])<dd class="text-xs text-ink-500">{{ __('normal :kmpl', ['kmpl' => $f['standard']]) }}</dd>@endif</div>
    <div><dt class="text-ink-500">{{ __('Fuel per km') }}</dt><dd class="money text-lg font-semibold">{{ $f['per_km'] !== null ? $m($f['per_km']) : '—' }}</dd></div>
  </dl>
  <p class="mt-3 text-xs text-ink-500">{{ __('Km per litre is measured from one full tank to the next: the km between them over the litres bought since.') }}</p>
</section>

<section class="card mt-6 overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Trip') }}</th><th class="text-end">{{ __('Litres') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Odometer') }}</th><th class="text-end">{{ __('Km per litre') }}</th><th>{{ __('Pump') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @forelse ($f['fills'] as $r)
          @php($e = $r->event)
          <tr>
            <td class="whitespace-nowrap text-sm">{{ $e->trusted_at->setTimezone(app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC')->format('d M Y H:i') }}</td>
            <td class="text-sm">@if ($e->trip)<a href="{{ route('trips.show', $e->trip_id) }}" class="docno whitespace-nowrap font-mono hover:text-brand-700">{{ $e->trip->trip_number }}</a>@endif</td>
            <td class="text-end text-sm">{{ number_format($r->litres, 1) }}@if ($r->full)<div class="text-xs text-ink-500">{{ __('full tank') }}</div>@endif</td>
            <td class="money text-end text-sm">{{ $r->rate !== null ? $m($r->rate) : '—' }}</td>
            <td class="money text-end text-sm">{{ $m($e->amount) }}</td>
            <td class="text-end text-sm">{{ $e->odometer ? number_format($e->odometer) : '—' }}</td>
            <td class="text-end text-sm {{ $r->kmpl && $f['standard'] && $r->kmpl < $f['standard'] * 0.95 ? 'font-semibold text-overdue' : '' }}">{{ $r->kmpl ?? '—' }}@if ($r->km)<div class="text-xs font-normal text-ink-500">{{ number_format($r->km) }} km</div>@endif</td>
            <td class="text-sm">{{ $r->pump ?? '—' }}</td>
            <td class="text-sm">{{ __(\App\Modules\Road\Models\RoadEvent::STATUSES[$e->status] ?? $e->status) }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="py-10 text-center text-ink-500">{{ __('No fuel in this period.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
