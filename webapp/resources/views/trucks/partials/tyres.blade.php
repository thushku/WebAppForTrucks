{{-- The truck's tyres by position (M38): fit one from the store, take one off, move or swap it. --}}
@php
  $odo = $truck->odometer_km;
  $bar = fn (?float $used) => $used === null ? 'bg-ink-300' : ($used >= 1 ? 'bg-overdue' : ($used >= \App\Modules\Fleet\Services\Tyres::NEAR_END ? 'bg-road-500' : 'bg-paid'));
  $positions = collect($axles)->pluck('positions')->flatten();
@endphp
@if ($errors->any())<p class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-overdue" role="alert">{{ $errors->first() }}</p>@endif
<section class="card p-6" data-tyre-map>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="font-semibold">{{ __('Tyres on the truck') }}</h3>
      <p class="text-sm text-ink-500">{{ trans_choice(':count of :total positions fitted|:count of :total positions fitted', $fitted->count(), ['total' => $positions->count()]) }} · {{ __('Odometer') }} {{ $odo !== null ? number_format($odo) . ' km' : '—' }}</p>
    </div>
    <a href="{{ route('trucks.tyres') }}" class="btn-secondary"><x-icon name="tyre" /> {{ __('Tyre store') }}</a>
  </div>

  <div class="mt-5 space-y-4">
    @foreach ($axles as $axle)
      <div class="grid gap-2 sm:grid-cols-[7rem_1fr] sm:items-start">
        <div class="pt-1 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $axle['name'] }}</div>
        <div class="grid gap-2 {{ count($axle['positions']) === 4 ? 'grid-cols-2 xl:grid-cols-4' : (count($axle['positions']) === 2 ? 'grid-cols-2 xl:max-w-[50%]' : 'grid-cols-1 sm:max-w-xs') }}">
          @foreach ($axle['positions'] as $pos)
            @php($t = $fitted[$pos] ?? null)
            <div class="min-w-0 rounded-xl border p-3 text-sm {{ $t ? 'border-ink-100 bg-white' : 'border-dashed border-ink-200 bg-ink-50' }}" data-position="{{ $pos }}">
              <div class="flex items-baseline justify-between gap-2 text-xs text-ink-500"><span class="shrink-0 whitespace-nowrap font-mono font-semibold">{{ $pos }}</span><span class="min-w-0 truncate" title="{{ $tyreService->positionName($pos) }}">{{ $tyreService->positionName($pos) }}</span></div>
              @if ($t)
                @php($km = $tyreService->kmRun($t))
                @php($used = $tyreService->used($t))
                <a href="{{ route('trucks.tyres.show', $t) }}" class="mt-1 block truncate font-mono font-semibold text-brand-700 hover:underline">{{ $t->serial_no }}</a>
                <div class="truncate text-xs text-ink-600">{{ $t->brand }} · {{ $t->size }}@if ($t->kind === 'retread') · {{ __('retreaded') }}@endif</div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100" aria-hidden="true"><div class="h-full {{ $bar($used) }}" style="width: {{ min(100, (int) round(($used ?? 0) * 100)) }}%"></div></div>
                <div class="mt-1 text-xs text-ink-500">{{ number_format($km) }} km{{ $t->expected_km ? ' / ' . number_format($t->expected_km) : '' }}@if ($used !== null) · {{ (int) round($used * 100) }}%@endif</div>
                <div class="text-xs text-ink-400">{{ __('On since :date at :km km', ['date' => $t->current?->fitted_on?->format('d M Y'), 'km' => number_format((int) $t->current?->fitted_odo)]) }}</div>
                @can('complaint.manage')
                  <details class="mt-2">
                    <summary class="cursor-pointer text-xs font-semibold text-brand-700">{{ __('Take off') }}</summary>
                    <form method="POST" action="{{ route('trucks.tyres.off', [$truck, $t]) }}" class="mt-2 space-y-2" novalidate>@csrf
                      <input type="date" name="removed_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input py-1.5 text-sm" aria-label="{{ __('Taken off on') }}" required>
                      <input type="number" name="odometer" value="{{ $odo }}" min="0" class="input py-1.5 text-sm" placeholder="{{ __('Odometer') }}" aria-label="{{ __('Odometer') }}" required>
                      <select name="reason" class="input py-1.5 text-sm" aria-label="{{ __('Why') }}">@foreach (\App\Modules\Fleet\Models\TyreFitment::REASONS as $code => [$label])<option value="{{ $code }}">{{ __($label) }}</option>@endforeach</select>
                      <input type="number" name="scrap_value" step="0.01" min="0" class="input py-1.5 text-sm" placeholder="{{ __('Scrap money, if scrapped') }}" aria-label="{{ __('Scrap money, if scrapped') }}">
                      <button class="btn-secondary w-full justify-center py-1.5 text-sm">{{ __('Take off') }}</button>
                    </form>
                  </details>
                  <details class="mt-1">
                    <summary class="cursor-pointer text-xs font-semibold text-brand-700">{{ __('Move or swap') }}</summary>
                    <form method="POST" action="{{ route('trucks.tyres.move', [$truck, $t]) }}" class="mt-2 space-y-2" novalidate>@csrf
                      <select name="position" class="input py-1.5 text-sm" aria-label="{{ __('To position') }}">@foreach ($positions as $p)@continue($p === $pos)<option value="{{ $p }}">{{ $p }} · {{ $tyreService->positionName($p) }}{{ isset($fitted[$p]) ? ' ⇄ ' . $fitted[$p]->serial_no : '' }}</option>@endforeach</select>
                      @if ($otherTrucks->isNotEmpty())
                        <select name="to_truck_id" class="input py-1.5 text-sm" aria-label="{{ __('To truck') }}"><option value="">{{ __('This truck') }}</option>@foreach ($otherTrucks as $id => $reg)<option value="{{ $id }}">{{ $reg }}</option>@endforeach</select>
                        <input type="number" name="new_odometer" min="0" class="input py-1.5 text-sm" placeholder="{{ __('The other truck’s odometer') }}" aria-label="{{ __('The other truck’s odometer') }}">
                      @endif
                      <input type="date" name="moved_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input py-1.5 text-sm" aria-label="{{ __('Moved on') }}" required>
                      <input type="number" name="odometer" value="{{ $odo }}" min="0" class="input py-1.5 text-sm" placeholder="{{ __('Odometer') }}" aria-label="{{ __('Odometer') }}" required>
                      <button class="btn-secondary w-full justify-center py-1.5 text-sm">{{ __('Move') }}</button>
                    </form>
                  </details>
                @endcan
              @else
                <div class="mt-1 text-xs text-ink-500">{{ __('Empty') }}</div>
                @can('complaint.manage')
                  @if ($store->isNotEmpty())
                    <details class="mt-2">
                      <summary class="cursor-pointer text-xs font-semibold text-brand-700">{{ __('Fit a tyre') }}</summary>
                      <form method="POST" action="{{ route('trucks.tyres.fit', $truck) }}" class="mt-2 space-y-2" novalidate>@csrf
                        <input type="hidden" name="position" value="{{ $pos }}">
                        <select name="tyre_id" class="input py-1.5 text-sm" aria-label="{{ __('Tyre') }}" required><option value="">{{ __('Choose') }}</option>@foreach ($store as $s)<option value="{{ $s->id }}">{{ $s->serial_no }} · {{ $s->brand }} {{ $s->size }}</option>@endforeach</select>
                        <input type="date" name="fitted_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input py-1.5 text-sm" aria-label="{{ __('Fitted on') }}" required>
                        <input type="number" name="odometer" value="{{ $odo }}" min="0" class="input py-1.5 text-sm" placeholder="{{ __('Odometer') }}" aria-label="{{ __('Odometer') }}" required>
                        <button class="btn-primary w-full justify-center py-1.5 text-sm">{{ __('Fit') }}</button>
                      </form>
                    </details>
                  @endif
                @endcan
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
  @can('complaint.manage')@if ($store->isEmpty())<p class="mt-4 text-sm text-ink-500">{{ __('No tyre in the store to fit.') }} <a href="{{ route('trucks.tyres') }}#add" class="font-semibold text-brand-700 hover:underline">{{ __('Add tyres') }}</a></p>@endif @endcan
</section>

<section class="card mt-6 overflow-hidden">
  <h3 class="px-6 pt-5 font-semibold">{{ __('Tyre history of this truck') }}</h3>
  <div class="relative mt-2 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Position') }}</th><th>{{ __('Tyre') }}</th><th>{{ __('Fitted') }}</th><th>{{ __('Taken off') }}</th><th class="text-end">{{ __('Km') }}</th><th>{{ __('Why') }}</th></tr></thead>
      <tbody>
        @forelse ($history as $f)
          <tr>
            <td class="whitespace-nowrap font-mono text-sm">{{ $f->position }}</td>
            <td class="text-sm"><a href="{{ route('trucks.tyres.show', $f->tyre_id) }}" class="font-mono text-brand-700 hover:underline">{{ $f->tyre?->serial_no }}</a><div class="text-xs text-ink-500">{{ $f->tyre?->brand }} {{ $f->tyre?->size }}</div></td>
            <td class="whitespace-nowrap text-sm">{{ $f->fitted_on->format('d M Y') }}<div class="text-xs text-ink-500">{{ number_format($f->fitted_odo) }} km</div></td>
            <td class="whitespace-nowrap text-sm">@if ($f->removed_on){{ $f->removed_on->format('d M Y') }}<div class="text-xs text-ink-500">{{ number_format($f->removed_odo) }} km</div>@else<span class="pill bg-emerald-50 text-paid">{{ __('On the truck') }}</span>@endif</td>
            <td class="text-end text-sm">{{ $f->km !== null ? number_format($f->km) : '—' }}</td>
            <td class="text-sm">{{ $f->reason ? __(\App\Modules\Fleet\Models\TyreFitment::REASONS[$f->reason][0] ?? $f->reason) : '' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-6 text-center text-sm text-ink-500">{{ __('No tyre fitted yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
