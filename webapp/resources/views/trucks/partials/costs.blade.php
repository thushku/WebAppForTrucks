{{-- What the truck earned and cost in the period (M39): the books, then the estimates the books do not hold by truck. --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $c = $activity;
@endphp
<section class="card p-6" data-truck-money>
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h3 class="font-semibold">{{ __('What the truck earned and cost') }}</h3>
      <p class="text-sm text-ink-500">{{ trans_choice(':count day|:count days', $c['days']) }} · {{ trans_choice(':count trip ended|:count trips ended', $c['trips']) }} · {{ number_format($c['km']) }} km</p>
    </div>
    @include('trucks.partials._period')
  </div>
  <dl class="mt-5 space-y-1.5 text-sm sm:max-w-xl">
    <div class="flex justify-between gap-3"><dt class="text-ink-500">{{ __('Income in the books') }}</dt><dd class="money">{{ $m($c['total_income']) }}</dd></div>
    <div class="flex justify-between gap-3"><dt class="text-ink-500">{{ __('Costs in the books') }}</dt><dd class="money">− {{ $m($c['total_expense']) }}</dd></div>
    <div class="flex justify-between gap-3 border-t border-ink-100 pt-1.5 font-semibold"><dt>{{ __('Profit in the books') }}</dt><dd class="money">{{ $m($c['profit']) }}</dd></div>
    @if ($c['counts'])
      @if ($c['estimates']['papers'] > 0)<div class="flex justify-between gap-3 pt-1.5"><dt class="text-ink-500">{{ __('Its papers for the days (estimate)') }}</dt><dd class="money">− {{ $m($c['estimates']['papers']) }}</dd></div>@endif
      @if ($c['estimates']['salary'] > 0)<div class="flex justify-between gap-3"><dt class="text-ink-500">{{ __('Usual driver’s salary for the days (estimate)') }}</dt><dd class="money">− {{ $m($c['estimates']['salary']) }}</dd></div>@endif
    @endif
    @if ($c['estimates']['tyres'] > 0)<div class="flex justify-between gap-3"><dt class="text-ink-500">{{ __('Tyre wear on its trips (estimate)') }}</dt><dd class="money">− {{ $m($c['estimates']['tyres']) }}</dd></div>@endif
    <div class="flex justify-between gap-3 border-t border-ink-100 pt-1.5 font-semibold {{ $c['net'] >= 0 ? 'text-paid' : 'text-overdue' }}"><dt>{{ __('Profit after the truck’s costs') }}</dt><dd class="money">{{ $m($c['net']) }}</dd></div>
    @if ($c['per_km'] !== null)<div class="flex justify-between gap-3 text-xs text-ink-500"><dt>{{ __('per km') }}</dt><dd class="money">{{ $m($c['per_km']) }}</dd></div>@endif
  </dl>
  <p class="mt-3 text-xs text-ink-500">{{ __('The books hold what was booked to the truck: its trips’ freight and costs, its repairs, EMI interest. A paper’s cost, the usual driver’s salary and tyre wear are not booked to a truck, so they are added here as estimates.') }}
    @can('accounts.view')<a href="{{ route('accounts.trucks', ['from' => $c['from']->toDateString(), 'to' => $c['to']->toDateString(), 'truck' => $truck->id]) }}" class="text-brand-700 hover:underline">{{ __('Truck profit in the books') }}</a>@endcan</p>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('By ledger') }}</h3>
    @foreach (['income' => __('Income'), 'expense' => __('Costs')] as $side => $title)
      <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $title }}</h4>
      @forelse ($c[$side] as $group => $ledgers)
        <div class="mt-2 text-xs text-ink-400">{{ $group }}</div>
        <ul class="text-sm">
          @foreach ($ledgers as $l)<li class="flex justify-between gap-3 py-0.5"><span>{{ $l->name }}</span><span class="money">{{ $m($l->amount) }}</span></li>@endforeach
        </ul>
      @empty
        <p class="mt-1 text-sm text-ink-500">{{ __('Nothing in this period.') }}</p>
      @endforelse
    @endforeach
  </section>
  <section class="card overflow-hidden">
    <h3 class="px-6 pt-5 font-semibold">{{ __('Month by month') }}</h3>
    <div class="relative mt-2 overflow-x-auto">
      <table class="table">
        <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Income') }}</th><th class="text-end">{{ __('Running') }}</th><th class="text-end">{{ __('Maintenance') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
        <tbody>
          @forelse ($c['months'] as $row)
            <tr>
              <td class="whitespace-nowrap text-sm">{{ \Carbon\CarbonImmutable::parse($row->month . '-01')->translatedFormat('M Y') }}</td>
              <td class="money text-end text-sm">{{ $m($row->income) }}</td><td class="money text-end text-sm">{{ $m($row->running) }}</td>
              <td class="money text-end text-sm">{{ $m($row->maintenance) }}</td><td class="money text-end text-sm font-semibold {{ $row->profit < 0 ? 'text-overdue' : '' }}">{{ $m($row->profit) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-6 text-center text-sm text-ink-500">{{ __('Nothing in the books for this truck in the period.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
