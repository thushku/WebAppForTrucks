<section class="card p-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="font-semibold">{{ __('Hired truck') }}</h3>
      <p class="text-sm text-ink-500">{{ $trip->supplier?->name }} · <span class="docno font-mono">{{ $trip->supplierVehicle?->reg_no }}</span></p>
    </div>
    @can('hire.manage')
      @if ($trip->hireChallans->where('status', '!=', 'cancelled')->isEmpty() && $trip->status !== 'cancelled')
        <a href="{{ route('trips.hire.create', $trip) }}" class="btn-primary"><x-icon name="file-text" /> {{ __('Make hire challan') }}</a>
      @endif
    @endcan
  </div>
  @foreach ($trip->hireChallans as $hc)
    <a href="{{ route('trips.hire.edit', [$trip, $hc]) }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-xl border border-ink-100 px-4 py-3 hover:border-brand-300">
      <span class="docno font-mono text-sm font-semibold">{{ $hc->number ?? __('Draft challan') }}</span>
      <span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Trips\Models\HireChallan::STATUSES[$hc->status]) }}</span>
      <span class="ms-auto text-sm">{{ __('Hire') }} <span class="money font-semibold">{{ \App\Support\Money::company($hc->hire_amount) }}</span> · {{ __('Balance') }} <span class="money font-semibold">{{ \App\Support\Money::company($hc->balance()) }}</span></span>
    </a>
  @endforeach
</section>
