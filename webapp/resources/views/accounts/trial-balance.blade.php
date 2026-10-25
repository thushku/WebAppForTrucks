@extends('layouts.app')
@section('title', __('Trial balance'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
@include('accounts._period', ['asOn' => true, 'from' => $to, 'csv' => route('accounts.trial-balance', ['to' => $to->toDateString(), 'format' => 'csv'])])

<section class="card overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-6">
    <h2 class="text-lg font-semibold">{{ __('Trial balance as on :date', ['date' => $to->format('d M Y')]) }}</h2>
    <span class="pill {{ $tb['balanced'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-overdue' }}">{{ $tb['balanced'] ? __('Balanced') : __('Not balanced') }}</span>
  </div>
  <div class="overflow-x-auto">
    <table class="table mt-3">
      <thead><tr><th>{{ __('Ledger') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
      @foreach ($tb['groups'] as $group => $ledgers)
        <tbody>
          <tr class="bg-ink-50/60"><td class="text-xs font-semibold uppercase tracking-wide text-ink-500" colspan="3">{{ $group }}</td></tr>
          @foreach ($ledgers as $l)
            <tr>
              <td class="text-sm"><a href="{{ route('ledgers.show', ['ledger' => $l->id, 'to' => $to->toDateString()]) }}" class="hover:text-brand-700">{{ $l->name }}</a></td>
              <td class="money text-end text-sm">{{ $l->balance > 0 ? $m($l->balance) : '' }}</td>
              <td class="money text-end text-sm">{{ $l->balance < 0 ? $m(-$l->balance) : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      @endforeach
      <tfoot><tr class="font-semibold"><td class="text-end">{{ __('Total') }}</td><td class="money text-end">{{ $m($tb['debit']) }}</td><td class="money text-end">{{ $m($tb['credit']) }}</td></tr></tfoot>
    </table>
  </div>
</section>
@endsection
