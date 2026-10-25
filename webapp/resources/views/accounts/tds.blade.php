@extends('layouts.app')
@section('title', __('TDS'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
<div class="flex gap-1.5" role="tablist">
  @foreach (['receivable' => __('Deducted by clients'), 'payable' => __('Deducted by us (lorry hire)')] as $key => $label)
    <a href="{{ route('accounts.tds', ['tab' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}</a>
  @endforeach
</div>
@include('accounts._period', ['keep' => ['tab' => $tab], 'csv' => route('accounts.tds', ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'format' => 'csv'])])
<p class="text-sm text-ink-500">{{ $tab === 'payable' ? __('For the quarterly TDS return: each challan’s TDS, or why none was deducted (the declaration of 10 trucks or fewer is still reported).') : __('Match these with Form 26AS / AIS each quarter; a difference from what the bill expected is shown.') }}</p>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      @if ($tab === 'payable')
        <thead><tr><th>{{ __('Quarter') }}</th><th>{{ __('Supplier') }}</th><th>PAN</th><th>{{ __('Challan') }}</th><th class="text-end">{{ __('Hire') }}</th><th>{{ __('Section') }}</th><th class="text-end">{{ __('TDS') }}</th><th>{{ __('No TDS because') }}</th></tr></thead>
        <tbody>
          @forelse ($rows as $r)
            <tr><td class="text-sm">{{ $r->quarter }}</td><td class="text-sm">{{ $r->party }}</td><td class="font-mono text-xs">{{ $r->pan ?? '—' }}</td><td class="font-mono text-xs">{{ $r->challan }}<div class="text-ink-500">{{ $r->date->format('d M Y') }}</div></td>
              <td class="money text-end text-sm">{{ $m($r->hire) }}</td><td class="text-sm">{{ $r->section }} {{ $r->rate ? '· ' . rtrim(rtrim(number_format($r->rate, 2), '0'), '.') . '%' : '' }}</td><td class="money text-end text-sm font-medium">{{ $m($r->tds) }}</td><td class="text-xs text-ink-500">{{ $r->nil_reason }}</td></tr>
          @empty
            <tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('No hire challans with TDS in this period.') }}</td></tr>
          @endforelse
        </tbody>
        @if ($rows->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="4">{{ __('Total') }}</td><td class="money text-end">{{ $m($rows->sum('hire')) }}</td><td></td><td class="money text-end">{{ $m($rows->sum('tds')) }}</td><td></td></tr></tfoot>@endif
      @else
        <thead><tr><th>{{ __('Quarter') }}</th><th>{{ __('Client') }}</th><th>PAN · TAN</th><th>{{ __('Bill') }}</th><th class="text-end">{{ __('Expected') }}</th><th class="text-end">{{ __('Deducted') }}</th><th>{{ __('Receipt') }}</th></tr></thead>
        <tbody>
          @forelse ($rows as $r)
            <tr><td class="text-sm">{{ $r->quarter }}</td><td class="text-sm">{{ $r->party }}</td><td class="font-mono text-xs">{{ $r->pan ?? '—' }}<div>{{ $r->tan }}</div></td>
              <td class="font-mono text-xs">{{ $r->invoice }}<div class="text-ink-500">{{ \Carbon\CarbonImmutable::parse($r->issue_date)->format('d M Y') }} · {{ $m($r->bill_total) }}</div></td>
              <td class="money text-end text-sm">{{ $m($r->tds_expected) }}</td>
              <td class="money text-end text-sm font-medium">{{ $m($r->tds) }}@if (abs($r->difference) >= 1)<div class="text-xs {{ $r->difference < 0 ? 'text-overdue' : 'text-partial' }}">{{ $r->difference < 0 ? __(':a less than expected', ['a' => $m(-$r->difference)]) : __(':a more than expected', ['a' => $m($r->difference)]) }}</div>@endif</td>
              <td class="font-mono text-xs">{{ $r->receipt }}<div class="text-ink-500">{{ \Carbon\CarbonImmutable::parse($r->receipt_date)->format('d M Y') }} · {{ $r->section }}</div></td></tr>
          @empty
            <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ __('No TDS deducted by clients in this period.') }}</td></tr>
          @endforelse
        </tbody>
        @if ($rows->isNotEmpty())<tfoot><tr class="font-semibold"><td colspan="4">{{ __('Total') }}</td><td class="money text-end">{{ $m($rows->sum(fn ($r) => (float) $r->tds_expected)) }}</td><td class="money text-end">{{ $m($rows->sum(fn ($r) => (float) $r->tds)) }}</td><td></td></tr></tfoot>@endif
      @endif
    </table>
  </div>
</section>
@endsection
