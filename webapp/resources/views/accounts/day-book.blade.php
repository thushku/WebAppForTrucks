@extends('layouts.app')
@section('title', __('Day book'))
@section('content')
@php($m = fn ($v) => (float) $v ? \App\Support\Money::company($v) : '')
@include('accounts._nav')
@include('accounts._period', ['csv' => route('accounts.day-book', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => 'csv'])])
@canany(['data.export', 'statement.export'])
  <p class="text-end text-sm"><a href="{{ route('accounts.tally', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="text-brand-700 hover:underline"><x-icon name="download" class="size-4" /> {{ __('Tally import file (XML) for this period') }}</a></p>
@endcanany

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
      <tbody>
        @forelse ($vouchers as $v)
          <tr class="bg-ink-50/60 {{ $v->isCancelled() ? 'opacity-60' : '' }}">
            <td class="whitespace-nowrap text-sm">{{ $v->voucher_date->format('d M Y') }}@if ($v->document_date)<div class="text-xs text-ink-500">{{ __('dated :d', ['d' => $v->document_date->format('d M')]) }}</div>@endif</td>
            <td><a href="{{ route('accounts.vouchers.show', $v) }}" class="whitespace-nowrap font-mono text-xs font-semibold hover:text-brand-700">{{ $v->number }}</a><div class="text-xs text-ink-500">{{ $v->typeLabel() }}{{ $v->isCancelled() ? ' · ' . __('cancelled') : '' }}</div></td>
            <td colspan="3" class="text-sm font-medium">{{ $v->narration }}@if ($v->reference) <span class="text-xs text-ink-500">· {{ $v->reference }}</span>@endif</td>
          </tr>
          @foreach ($v->lines as $l)
            <tr class="{{ $v->isCancelled() ? 'opacity-60' : '' }}">
              <td></td><td></td>
              <td class="text-sm {{ (float) $l->credit ? 'ps-10' : 'ps-6' }}">{{ $l->ledger->name }}@if ($l->bucket) <span class="text-xs text-ink-500">({{ __(\App\Modules\Accounts\Models\VoucherLine::BUCKETS[$l->bucket] ?? $l->bucket) }})</span>@endif @if ($l->note)<span class="text-xs text-ink-500"> · {{ $l->note }}</span>@endif</td>
              <td class="money text-end text-sm">{{ $m($l->debit) }}</td><td class="money text-end text-sm">{{ $m($l->credit) }}</td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ __('No vouchers in this period.') }}</td></tr>
        @endforelse
      </tbody>
      @if ($vouchers->isNotEmpty())
        <tfoot><tr class="font-semibold"><td colspan="3" class="text-end">{{ trans_choice(':count voucher|:count vouchers', $vouchers->count()) }}</td><td class="money text-end">{{ $m($vouchers->sum('total')) }}</td><td class="money text-end">{{ $m($vouchers->sum('total')) }}</td></tr></tfoot>
      @endif
    </table>
  </div>
</section>
@endsection
