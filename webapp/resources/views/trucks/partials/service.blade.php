<section class="card p-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h3 class="font-semibold">{{ __('Service schedules') }}</h3>
    @can('complaint.manage')<a href="{{ route('trucks.service.create', $truck) }}" class="btn-primary"><x-icon name="plus" /> {{ __('New service job') }}</a>@endcan
  </div>
  <div class="mt-3 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Service') }}</th><th>{{ __('Every') }}</th><th>{{ __('Last done') }}</th><th>{{ __('Next due') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($schedules as $s)
          @php($state = $s->state($truck->odometer_km))
          <tr>
            <td class="font-medium">{{ $s->name }}</td>
            <td class="text-sm">{{ collect([$s->every_km ? number_format($s->every_km) . ' km' : null, $s->every_days ? trans_choice(':count day|:count days', $s->every_days) : null])->filter()->join(' / ') }}</td>
            <td class="text-sm">{{ collect([$s->last_done_km !== null ? number_format($s->last_done_km) . ' km' : null, $s->last_done_on?->format('d M Y')])->filter()->join(' · ') ?: '—' }}</td>
            <td><span class="pill {{ ['overdue' => 'bg-red-50 text-overdue', 'soon' => 'bg-amber-50 text-partial', 'ok' => 'bg-emerald-50 text-paid'][$state] ?? 'bg-ink-100 text-ink-500' }}">{{ collect([$s->dueKm() ? number_format($s->dueKm()) . ' km' : null, $s->dueOn()?->format('d M Y')])->filter()->join(' / ') ?: '—' }}</span></td>
            <td class="text-end">@can('complaint.manage')<a href="{{ route('trucks.service.create', [$truck, 'schedule' => $s->id]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Record service') }}</a>@endcan</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-6 text-center text-sm text-ink-500">{{ __('No schedules yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @can('complaint.manage')
    <form method="POST" action="{{ route('trucks.schedules.store', $truck) }}" class="mt-4 grid gap-2 sm:grid-cols-6" novalidate>
      @csrf
      <input name="name" class="input sm:col-span-2" placeholder="{{ __('Engine oil, general service…') }}" maxlength="80" aria-label="{{ __('Service name') }}" value="{{ old('name') }}">
      <input name="every_km" type="number" class="input" placeholder="{{ __('Every km') }}" aria-label="{{ __('Every km') }}" value="{{ old('every_km') }}">
      <input name="every_days" type="number" class="input" placeholder="{{ __('Every days') }}" aria-label="{{ __('Every days') }}" value="{{ old('every_days') }}">
      <input name="last_done_km" type="number" class="input" placeholder="{{ __('Last done at km') }}" aria-label="{{ __('Last done at km') }}" value="{{ old('last_done_km') }}">
      <input name="last_done_on" type="date" class="input" aria-label="{{ __('Last done on') }}" value="{{ old('last_done_on') }}">
      <div class="sm:col-span-6 flex items-center justify-between">
        <span class="error">@error('every_km'){{ $message }}@enderror @error('name'){{ $message }}@enderror</span>
        <button class="btn-secondary"><x-icon name="plus" /> {{ __('Add schedule') }}</button>
      </div>
    </form>
  @endcan
</section>

<section class="card overflow-hidden">
  <div class="p-6 pb-3"><h3 class="font-semibold">{{ __('Service history') }}</h3></div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Workshop') }}</th><th class="text-end">{{ __('Odometer') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @forelse ($jobs as $j)
          <tr class="{{ $j->status === 'cancelled' ? 'opacity-50' : '' }}">
            <td><a href="{{ route('trucks.service.edit', [$truck, $j]) }}" class="docno font-mono text-xs font-semibold text-brand-700 hover:underline">{{ $j->number }}</a></td>
            <td class="nowrap text-sm">{{ $j->job_date->format('d M Y') }}</td>
            <td class="text-sm">{{ __(\App\Modules\Fleet\Models\ServiceJob::KINDS[$j->kind]) }}</td>
            <td class="text-sm">{{ $j->vendor?->name ?? '—' }}</td>
            <td class="nowrap text-end text-sm">{{ $j->odometer_km ? number_format($j->odometer_km) : '—' }}</td>
            <td class="money text-end font-semibold">{{ \App\Support\Money::company($j->total) }}</td>
            <td><span class="pill {{ $j->status === 'done' ? 'bg-emerald-50 text-paid' : ($j->status === 'open' ? 'bg-amber-50 text-partial' : 'bg-ink-100 text-ink-500') }}">{{ __(\App\Modules\Fleet\Models\ServiceJob::STATUSES[$j->status]) }}</span></td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ __('No service jobs yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
