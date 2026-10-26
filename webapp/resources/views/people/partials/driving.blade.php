{{-- A person's time at the wheel in the period (M41): shifts, day by day, against the limit without a break. --}}
@php
  $d = $activity;
  $hm = fn (int $min) => __(':h h :m min', ['h' => intdiv($min, 60), 'm' => $min % 60]);
  $tz = app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC';
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <h3 class="font-semibold">{{ __('Driving hours of :name', ['name' => $person->name]) }}</h3>
    @include('people.partials._period')
  </div>
  <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 xl:grid-cols-5">
    <div><dt class="text-ink-500">{{ __('At the wheel') }}</dt><dd class="text-lg font-semibold">{{ $hm($d['minutes']) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Days driven') }}</dt><dd class="text-lg font-semibold">{{ $d['days_driven'] }}</dd>@if ($d['days_driven'])<dd class="text-xs text-ink-500">{{ __(':time a day', ['time' => $hm(intdiv($d['minutes'], $d['days_driven']))]) }}</dd>@endif</div>
    <div><dt class="text-ink-500">{{ __('Km driven') }}</dt><dd class="text-lg font-semibold">{{ number_format($d['km']) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Longest stretch') }}</dt><dd class="text-lg font-semibold {{ $d['longest'] > $d['limit'] ? 'text-overdue' : '' }}">{{ $hm($d['longest']) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Over the limit') }}</dt><dd class="text-lg font-semibold {{ $d['over'] ? 'text-overdue' : '' }}">{{ $d['over'] }}</dd><dd class="text-xs text-ink-500">{{ __('limit :time without a break', ['time' => $hm($d['limit'])]) }}</dd></div>
  </dl>
</section>

<div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
  <section class="card overflow-hidden">
    <h3 class="px-6 pt-5 font-semibold">{{ __('Shifts') }}</h3>
    <div class="relative mt-2 overflow-x-auto">
      <table class="table">
        <thead><tr><th>{{ __('Started') }}</th><th>{{ __('Ended') }}</th><th>{{ __('Trip') }}</th><th class="text-end">{{ __('At the wheel') }}</th><th class="text-end">{{ __('Km') }}</th></tr></thead>
        <tbody>
          @forelse ($d['shifts'] as $r)
            <tr>
              <td class="whitespace-nowrap text-sm">{{ $r->shift->started_at->setTimezone($tz)->format('d M H:i') }}</td>
              <td class="whitespace-nowrap text-sm">{{ $r->open ? __('Driving now') : $r->shift->ended_at->setTimezone($tz)->format('d M H:i') }}</td>
              <td class="text-sm">@if ($r->shift->trip)<a href="{{ route('trips.show', $r->shift->trip_id) }}" class="docno whitespace-nowrap font-mono hover:text-brand-700">{{ $r->shift->trip->trip_number }}</a>@endif</td>
              <td class="whitespace-nowrap text-end text-sm {{ $r->over ? 'font-semibold text-overdue' : '' }}">{{ $hm($r->minutes) }}</td>
              <td class="text-end text-sm">{{ $r->km !== null ? number_format($r->km) : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ __('No driving in this period.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Day by day') }}</h3>
    <ul class="mt-3 space-y-1.5 text-sm">
      @forelse ($d['days']->take(31) as $day => $minutes)
        <li class="flex justify-between gap-3"><span class="text-ink-500">{{ \Carbon\CarbonImmutable::parse($day)->translatedFormat('D d M') }}</span><span>{{ $hm($minutes) }}</span></li>
      @empty
        <li class="text-ink-500">—</li>
      @endforelse
    </ul>
  </section>
</div>
