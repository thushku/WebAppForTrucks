<section class="card p-6">
  <h3 class="font-semibold">{{ __('Broker commission') }}</h3>
  <ul class="mt-3 space-y-1.5 text-sm">
    @forelse ($trip->commissions as $bc)
      <li class="flex items-center justify-between gap-2">
        <span>{{ $bc->broker->name }} <span class="text-xs text-ink-500">· {{ __(\App\Modules\Trips\Models\BrokerCommission::DIRECTIONS[$bc->direction]) }}{{ $bc->basis === 'percent' ? ' · ' . rtrim(rtrim((string) $bc->percent, '0'), '.') . '%' : '' }}</span></span>
        <span class="flex items-center gap-1"><span class="money {{ $bc->direction === 'receivable' ? 'text-paid' : '' }}">{{ \App\Support\Money::company($bc->amount) }}</span>
          @if (! $bc->hire_challan_id && ! in_array($trip->status, ['closed', 'cancelled'], true))
            @can('trip.create')<form method="POST" action="{{ route('trips.commissions.destroy', [$trip, $bc]) }}">@csrf @method('DELETE')<button class="btn-ghost px-1.5 py-1" aria-label="{{ __('Remove') }}"><x-icon name="x" class="size-3.5" /></button></form>@endcan
          @endif
        </span>
      </li>
    @empty
      <li class="text-ink-500">{{ __('None.') }}</li>
    @endforelse
  </ul>
  @can('trip.create')
    @php($brokers = \App\Modules\Parties\Models\Party::active()->withRole('broker')->orderBy('name')->pluck('name', 'id'))
    @if ($brokers->isNotEmpty() && ! in_array($trip->status, ['closed', 'cancelled'], true))
      <form method="POST" action="{{ route('trips.commissions.store', $trip) }}" class="mt-4 grid gap-2" novalidate>
        @csrf
        <select name="broker_party_id" class="input py-2" aria-label="{{ __('Broker') }}">@foreach ($brokers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
        <select name="direction" class="input py-2" aria-label="{{ __('Direction') }}"><option value="payable">{{ __('We pay the broker (load broker)') }}</option><option value="receivable">{{ __('We earn it (we acted as broker)') }}</option></select>
        <div class="grid grid-cols-[7rem_1fr] gap-2">
          <select name="basis" class="input py-2" aria-label="{{ __('Basis') }}"><option value="fixed">{{ __('Amount') }}</option><option value="percent">{{ __('% of freight') }}</option></select>
          <div class="grid grid-cols-2 gap-2"><input name="amount" type="number" min="1" step="0.01" class="input py-2" placeholder="₹" aria-label="{{ __('Amount') }}"><input name="percent" type="number" min="0.01" step="0.01" class="input py-2" placeholder="%" aria-label="{{ __('Percent') }}"></div>
        </div>
        @foreach (['broker_party_id', 'amount', 'percent', 'commission'] as $f)@error($f)<span class="error">{{ $message }}</span>@enderror @endforeach
        <button class="btn-secondary">{{ __('Add commission') }}</button>
      </form>
    @endif
  @endcan
</section>
