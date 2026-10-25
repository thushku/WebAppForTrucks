@extends('layouts.app')
@section('title', __('Balance sheet'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
@include('accounts._period', ['asOn' => true, 'from' => $to])

<div class="grid gap-6 xl:grid-cols-2">
  @foreach (['liabilities' => __('Capital and liabilities'), 'assets' => __('Assets')] as $side => $title)
    <section class="card overflow-hidden">
      <h2 class="px-6 pt-6 text-lg font-semibold">{{ $title }}</h2>
      <table class="table mt-2">
        @foreach ($bs[$side] as $group => $ledgers)
          <tbody>
            <tr class="bg-ink-50/60"><td class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $group }}</td><td class="money text-end text-sm font-semibold">{{ $m($ledgers->sum('amount')) }}</td></tr>
            @foreach ($ledgers as $l)
              <tr><td class="ps-8 text-sm"><a href="{{ route('ledgers.show', ['ledger' => $l->id, 'to' => $to->toDateString()]) }}" class="hover:text-brand-700">{{ $l->name }}</a></td><td class="money text-end text-sm">{{ $m($l->amount) }}</td></tr>
            @endforeach
          </tbody>
        @endforeach
        @if ($side === 'liabilities')
          <tbody><tr><td class="text-sm font-medium">{{ $bs['profit'] < 0 ? __('Loss so far') : __('Profit so far') }}</td><td class="money text-end text-sm font-medium">{{ $m($bs['profit']) }}</td></tr></tbody>
        @endif
        <tfoot><tr class="font-semibold"><td>{{ __('Total') }}</td><td class="money text-end">{{ $m($side === 'assets' ? $bs['total_assets'] : $bs['total_liabilities']) }}</td></tr></tfoot>
      </table>
    </section>
  @endforeach
</div>
<p class="text-xs text-ink-500">{{ __('A simple balance sheet from the book. The statutory format (Schedule III in India, IFRS in the UAE) comes with the year-end work.') }}</p>
@endsection
