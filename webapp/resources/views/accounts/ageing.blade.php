@extends('layouts.app')
@section('title', __('Ageing'))
@use('App\Modules\Accounts\Services\Registers')
@section('content')
@php($m = fn ($v) => (float) $v ? \App\Support\Money::company($v) : '—')
@include('accounts._nav')
<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
  <div class="flex gap-1.5" role="tablist">
    @foreach (['receivables' => __('Clients owe you'), 'payables' => __('You owe truck suppliers')] as $key => $label)
      <a href="{{ route('accounts.ageing', ['tab' => $key, 'to' => $asOf->toDateString()]) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}</a>
    @endforeach
  </div>
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div><label class="label" for="ag_to">{{ __('As on') }}</label><input id="ag_to" type="date" name="to" value="{{ $asOf->toDateString() }}" class="input"></div>
  @if ($tab === 'receivables')
    <div><label class="label" for="ag_basis">{{ __('Count days from') }}</label><select id="ag_basis" name="basis" class="input"><option value="due">{{ __('The due date') }}</option><option value="bill" @selected($basis === 'bill')>{{ __('The bill date') }}</option></select></div>
  @endif
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <a href="{{ route('accounts.ageing', ['tab' => $tab, 'basis' => $basis, 'to' => $asOf->toDateString(), 'format' => 'csv']) }}" class="btn-ghost ms-auto text-sm"><x-icon name="download" class="size-4" /> {{ __('Excel (CSV)') }}</a>
</form>
<p class="text-sm text-ink-500">{{ $tab === 'payables' ? __('Hire still owed on issued challans, by the challan’s age. Pay the balance once the POD is in.') : __('Open bills by how long they are overdue. Advances the client has paid but not yet set against a bill are shown beside.') }}</p>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ $tab === 'payables' ? __('Supplier') : __('Client') }}</th>@foreach (Registers::SLABS as $key => $label)@continue($tab === 'payables' && $key === 'not_due')<th class="text-end">{{ __($label) }}</th>@endforeach<th class="text-end">{{ __('Total') }}</th>@if ($tab === 'receivables')<th class="text-end">{{ __('Advance') }}</th>@endif</tr></thead>
      @forelse ($rows as $r)
        <tbody>
          <tr class="bg-ink-50/40">
            <td class="font-medium">{{ $r['party']->name }}</td>
            @foreach (array_keys(Registers::SLABS) as $key)@continue($tab === 'payables' && $key === 'not_due')<td class="money text-end text-sm {{ $key === 'over' && $r[$key] > 0 ? 'font-semibold text-overdue' : '' }}">{{ $m($r[$key]) }}</td>@endforeach
            <td class="money text-end text-sm font-semibold">{{ $m($r['total']) }}</td>
            @if ($tab === 'receivables')<td class="money text-end text-sm text-ink-500">{{ $m($r['advance']) }}</td>@endif
          </tr>
          @foreach ($r['bills'] as $b)
            <tr class="text-xs text-ink-500">
              <td class="ps-8">@if ($tab === 'payables')<a href="{{ route('trips.show', $b['challan']->trip_id) }}" class="font-mono hover:text-brand-700">{{ $b['challan']->number }}</a> · {{ $b['challan']->challan_date->format('d M') }} · {{ $b['challan']->vehicle_reg }}
                @else<a href="{{ route('invoices.show', $b['invoice']) }}" class="font-mono hover:text-brand-700">{{ $b['invoice']->number }}</a> · {{ __('due :d', ['d' => $b['invoice']->due_date->format('d M')]) }}@endif</td>
              @foreach (array_keys(Registers::SLABS) as $key)@continue($tab === 'payables' && $key === 'not_due')<td class="money text-end">{{ $b['slab'] === $key ? $m($tab === 'payables' ? $b['balance'] : $b['invoice']->balance_due) : '' }}</td>@endforeach
              <td class="text-end">{{ $b['days'] > 0 || $tab === 'payables' ? trans_choice(':count day|:count days', max(0, $b['days'])) : __('not due') }}</td>
              @if ($tab === 'receivables')<td></td>@endif
            </tr>
          @endforeach
        </tbody>
      @empty
        <tbody><tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('Nothing outstanding.') }}</td></tr></tbody>
      @endforelse
      @if ($rows->isNotEmpty())
        <tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td>@foreach (array_keys(Registers::SLABS) as $key)@continue($tab === 'payables' && $key === 'not_due')<td class="money text-end">{{ $m($rows->sum($key)) }}</td>@endforeach<td class="money text-end">{{ $m($rows->sum('total')) }}</td>@if ($tab === 'receivables')<td class="money text-end">{{ $m($rows->sum('advance')) }}</td>@endif</tr></tfoot>
      @endif
    </table>
  </div>
</section>
@endsection
