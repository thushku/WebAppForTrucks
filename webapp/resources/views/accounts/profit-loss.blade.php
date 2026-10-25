@extends('layouts.app')
@section('title', __('Profit and loss'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
  <div><label class="label" for="pl_from">{{ __('From date') }}</label><input id="pl_from" type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
  <div><label class="label" for="pl_to">{{ __('To date') }}</label><input id="pl_to" type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
  <div><label class="label" for="pl_truck">{{ __('Truck') }}</label><select id="pl_truck" name="truck_id" class="input w-44"><option value="">{{ __('All') }}</option>@foreach ($trucks as $id => $reg)<option value="{{ $id }}" @selected(($centres['truck_id'] ?? null) == $id)>{{ $reg }}</option>@endforeach</select></div>
  @if ($branches->count() > 1)
    <div><label class="label" for="pl_branch">{{ __('Branch') }}</label><select id="pl_branch" name="branch_id" class="input w-44"><option value="">{{ __('All') }}</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected(($centres['branch_id'] ?? null) == $id)>{{ $name }}</option>@endforeach</select></div>
  @endif
  @if ($trip)<input type="hidden" name="trip_id" value="{{ $trip->id }}">@endif
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <span class="text-xs text-ink-500">{{ $fy->label() }}</span>
</form>
@if ($trip)<p class="text-sm">{{ __('Trip :trip only.', ['trip' => $trip->trip_number]) }} <a href="{{ route('accounts.profit-loss', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-brand-700 hover:underline">{{ __('Whole company') }}</a></p>@endif

<div class="grid gap-6 xl:grid-cols-2">
  @foreach (['income' => __('Income'), 'expense' => __('Expenses')] as $side => $title)
    <section class="card overflow-hidden">
      <h2 class="px-6 pt-6 text-lg font-semibold">{{ $title }}</h2>
      <table class="table mt-2">
        @forelse ($pl[$side] as $group => $ledgers)
          <tbody>
            <tr class="bg-ink-50/60"><td class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $group }}</td><td class="money text-end text-sm font-semibold">{{ $m($ledgers->sum('amount')) }}</td></tr>
            @foreach ($ledgers as $l)
              <tr><td class="ps-8 text-sm"><a href="{{ route('ledgers.show', ['ledger' => $l->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="hover:text-brand-700">{{ $l->name }}</a></td><td class="money text-end text-sm">{{ $m($l->amount) }}</td></tr>
            @endforeach
          </tbody>
        @empty
          <tbody><tr><td class="py-6 text-center text-sm text-ink-500" colspan="2">{{ __('None in this period.') }}</td></tr></tbody>
        @endforelse
        <tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td><td class="money text-end">{{ $m($side === 'income' ? $pl['total_income'] : $pl['total_expense']) }}</td></tr></tfoot>
      </table>
    </section>
  @endforeach
</div>
<div class="rounded-2xl px-6 py-4 text-lg font-semibold {{ $pl['profit'] < 0 ? 'bg-red-50 text-overdue' : 'bg-emerald-50 text-emerald-800' }}">
  {{ $pl['profit'] < 0 ? __('Loss') : __('Profit') }}: <span class="money">{{ $m(abs($pl['profit'])) }}</span>
</div>
@endsection
