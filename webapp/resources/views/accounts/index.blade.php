@extends('layouts.app')
@section('title', __('Accounts'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
@include('accounts._period')

@if ($lockedUntil)
  <p class="flex items-center gap-2 rounded-2xl bg-ink-50 px-4 py-2 text-sm text-ink-700"><x-icon name="lock" class="size-4" /> {{ __('The books are locked up to :date. Entries for those days post on the first open day.', ['date' => $lockedUntil->format('d M Y')]) }}</p>
@endif

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <div class="stat"><div class="stat-label">{{ __('Profit for the period') }}</div><div class="stat-value money {{ $pl['profit'] < 0 ? 'text-overdue' : '' }}">{{ $m($pl['profit']) }}</div>
    <div class="mt-1 text-xs text-ink-500">{{ __('Income :i · expenses :e', ['i' => $m($pl['total_income']), 'e' => $m($pl['total_expense'])]) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Clients owe you') }}</div><div class="stat-value money">{{ $m($receivable) }}</div><div class="mt-1 text-xs text-ink-500"><a href="{{ route('ledgers.index') }}" class="text-brand-700 hover:underline">{{ __('Client ledgers') }}</a></div></div>
  <div class="stat"><div class="stat-label">{{ __('You owe suppliers and vendors') }}</div><div class="stat-value money">{{ $m($payable) }}</div><div class="mt-1 text-xs text-ink-500"><a href="{{ route('ledgers.index', ['tab' => 'suppliers']) }}" class="text-brand-700 hover:underline">{{ __('Their ledgers') }}</a></div></div>
  <div class="stat"><div class="stat-label">{{ __('Cash · bank') }}</div><div class="stat-value money">{{ $m($cash + $bank) }}</div>
    <div class="mt-1 text-xs text-ink-500">{{ __('Cash :c · bank :b · with crew :w', ['c' => $m($cash), 'b' => $m($bank), 'w' => $m($withCrew)]) }}</div></div>
</div>

<div class="grid gap-6 xl:grid-cols-2">
  <section class="card p-6">
    <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">{{ __('Trial balance') }}</h2><a href="{{ route('accounts.trial-balance', ['to' => $to->toDateString()]) }}" class="text-sm text-brand-700 hover:underline">{{ __('View') }}</a></div>
    <p class="mt-2 text-sm {{ $tb['balanced'] ? 'text-emerald-700' : 'text-overdue' }}">
      {{ $tb['balanced'] ? __('Debits and credits agree: :amount on each side.', ['amount' => $m($tb['debit'])]) : __('Debits :d and credits :c do not agree — tell support.', ['d' => $m($tb['debit']), 'c' => $m($tb['credit'])]) }}</p>
    <dl class="mt-3 space-y-1 text-sm">
      @foreach ($pl['income'] as $group => $ledgers)<div class="flex justify-between"><dt>{{ $group }}</dt><dd class="money">{{ $m($ledgers->sum('amount')) }}</dd></div>@endforeach
      @foreach ($pl['expense'] as $group => $ledgers)<div class="flex justify-between text-ink-700"><dt>{{ $group }}</dt><dd class="money">− {{ $m($ledgers->sum('amount')) }}</dd></div>@endforeach
    </dl>
  </section>
  <section class="card overflow-hidden">
    <div class="flex items-center justify-between px-6 pt-6"><h2 class="text-lg font-semibold">{{ __('Latest vouchers') }}</h2><a href="{{ route('accounts.day-book') }}" class="text-sm text-brand-700 hover:underline">{{ __('Day book') }}</a></div>
    <table class="table mt-2">
      <tbody>
        @forelse ($recent as $v)
          <tr class="{{ $v->isCancelled() ? 'opacity-60' : '' }}">
            <td><a href="{{ route('accounts.vouchers.show', $v) }}" class="whitespace-nowrap font-mono text-xs font-semibold hover:text-brand-700">{{ $v->number }}</a><div class="text-xs text-ink-500">{{ $v->voucher_date->format('d M') }} · {{ $v->typeLabel() }}</div></td>
            <td class="text-sm">{{ \Illuminate\Support\Str::limit($v->narration, 70) }}</td>
            <td class="money text-end text-sm">{{ $m($v->total) }}</td>
          </tr>
        @empty
          <tr><td class="py-8 text-center text-ink-500">{{ __('Nothing posted yet. Bills, receipts, advances and approved road entries post here by themselves.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>
@endsection
