@extends('layouts.app')
@section('title', $ledger->name)
@use('App\Http\Controllers\Accounts\LedgerController')
@use('App\Modules\Accounts\Models\VoucherLine')
@section('content')
@php
  $m = fn ($v) => (float) $v ? \App\Support\Money::company($v) : '';
  $bal = fn ($v) => \App\Support\Money::company(abs($v)) . ' ' . ($v > 0.0049 ? __('Dr') : ($v < -0.0049 ? __('Cr') : ''));
  $s = $statement;
  $query = array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'bucket' => $bucket]);
@endphp
<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <a href="{{ in_array($ledger->kind, ['party', 'person'], true) ? route('ledgers.index', ['tab' => $ledger->kind === 'person' ? 'crew' : ($ledger->group->code === 'sundry_debtors' ? 'clients' : 'suppliers')]) : route('accounts.chart') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ in_array($ledger->kind, ['party', 'person'], true) ? __('Ledgers') : __('Chart of accounts') }}</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $ledger->name }} <span class="text-base font-normal text-ink-500">· {{ $ledger->group->name }}</span></h1>
    <p class="mt-1 text-lg font-medium {{ $s['closing'] > 0.004 ? 'text-emerald-700' : ($s['closing'] < -0.004 ? 'text-overdue' : 'text-ink-500') }}">{{ LedgerController::words($ledger->kind, $ledger->name, $s['closing']) }}</p>
  </div>
  <div class="flex flex-wrap gap-2">
    @if ($ledger->party)<a href="{{ route('parties.show', $ledger->party) }}" class="btn-secondary text-sm">{{ __('Party') }}</a>@endif
    @if ($ledger->person)<a href="{{ route('people.show', $ledger->person) }}" class="btn-secondary text-sm">{{ __('Person') }}</a>@endif
    @if ($s['closing'] < -0.004 && in_array($ledger->kind, ['party', 'person'], true))
      @can('accounts.post')<a href="{{ route('accounts.payments.create', ['ledger' => $ledger->id]) }}" class="btn-primary text-sm"><x-icon name="wallet" class="size-4" /> {{ __('Pay') }}</a>@endcan
    @endif
    @canany(['statement.export', 'accounts.view'])
      <a href="{{ route('ledgers.export', ['ledger' => $ledger] + $query) }}" class="btn-secondary text-sm"><x-icon name="download" class="size-4" /> {{ __('Excel (CSV)') }}</a>
      <a href="{{ route('ledgers.print', ['ledger' => $ledger] + $query) }}" target="_blank" rel="noopener" class="btn-secondary text-sm"><x-icon name="printer" class="size-4" /> {{ __('Statement') }}</a>
      <a href="{{ route('ledgers.pdf', ['ledger' => $ledger] + $query) }}" class="btn-secondary text-sm"><x-icon name="download" class="size-4" /> {{ __('PDF') }}</a>
    @endcanany
  </div>
</div>

@canany(['statement.export', 'accounts.view'])
  @if (in_array($ledger->kind, ['party', 'person'], true))
    <details class="card p-4" @if ($errors->hasAny(['to_email', 'subject', 'body'])) open @endif>
      <summary class="cursor-pointer font-medium"><x-icon name="mail" class="inline size-4 text-brand-700" /> {{ __('Email this statement') }}</summary>
      <form method="POST" action="{{ route('ledgers.email', ['ledger' => $ledger] + $query) }}" class="mt-3 grid gap-3 sm:max-w-2xl">
        @csrf
        <div><label class="label" for="st_to">{{ __('To') }}</label><input id="st_to" name="to_email" type="email" class="input" value="{{ old('to_email', $email['to']) }}" required>@error('to_email')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="st_subject">{{ __('Subject') }}</label><input id="st_subject" name="subject" class="input" value="{{ old('subject', $email['subject']) }}" maxlength="200" required></div>
        <div><label class="label" for="st_body">{{ __('Message') }}</label><textarea id="st_body" name="body" rows="6" class="input text-sm" maxlength="5000" required>{{ old('body', $email['body']) }}</textarea></div>
        <p class="text-xs text-ink-500">{{ __('The statement for :from – :to goes attached.', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]) }}</p>
        <div><button class="btn-primary"><x-icon name="send" /> {{ __('Send') }}</button></div>
      </form>
    </details>
  @endif
@endcanany

@if ($buckets)
  <div class="grid gap-4 sm:grid-cols-3">
    <a href="{{ route('ledgers.show', ['ledger' => $ledger, 'bucket' => 'trip_cash'] + $query) }}" class="stat {{ $bucket === 'trip_cash' ? 'ring-2 ring-brand-300' : '' }}"><div class="stat-label">{{ __('Trip cash') }}</div><div class="stat-value money">{{ $bal($buckets['trip_cash']) }}</div><div class="mt-1 text-xs text-ink-500">{{ $buckets['trip_cash'] > 0 ? __('Company money still with :name', ['name' => $ledger->name]) : __('Spent from their own pocket, not yet paid back') }}</div></a>
    <a href="{{ route('ledgers.show', ['ledger' => $ledger, 'bucket' => 'earnings'] + $query) }}" class="stat {{ $bucket === 'earnings' ? 'ring-2 ring-brand-300' : '' }}"><div class="stat-label">{{ __('Earnings') }}</div><div class="stat-value money">{{ $bal($buckets['earnings']) }}</div><div class="mt-1 text-xs text-ink-500">{{ __('Bata, salary and incentives less deductions') }}</div></a>
    <a href="{{ route('ledgers.show', ['ledger' => $ledger] + array_diff_key($query, ['bucket' => 1])) }}" class="stat {{ ! $bucket ? 'ring-2 ring-brand-300' : '' }}"><div class="stat-label">{{ __('Net balance due') }}</div><div class="stat-value money">{{ $bal($buckets['net']) }}</div><div class="mt-1 text-xs text-ink-500">{{ LedgerController::words('person', $ledger->name, $buckets['net']) }}</div></a>
  </div>
@endif

@include('accounts._period', ['keep' => array_filter(['bucket' => $bucket])])

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
      <tbody>
        <tr class="bg-ink-50/60"><td class="text-sm">{{ $from->format('d M Y') }}</td><td></td><td class="text-sm font-medium">{{ __('Opening balance') }}</td><td></td><td></td><td class="money text-end text-sm font-medium">{{ $bal($s['opening']) }}</td></tr>
        @forelse ($s['rows'] as $r)
          <tr class="{{ $r->status === 'cancelled' ? 'opacity-60' : '' }}">
            <td class="whitespace-nowrap text-sm">{{ \Carbon\CarbonImmutable::parse($r->voucher_date)->format('d M Y') }}</td>
            <td><a href="{{ route('accounts.vouchers.show', $r->voucher_id) }}" class="whitespace-nowrap font-mono text-xs font-semibold hover:text-brand-700">{{ $r->number }}</a><div class="text-xs text-ink-500">{{ __(\App\Modules\Accounts\Models\Voucher::TYPES[$r->voucher_type] ?? $r->voucher_type) }}</div></td>
            <td class="text-sm">{{ $r->narration }}@if ($r->bucket && ! $bucket) <span class="text-xs text-ink-500">({{ __(VoucherLine::BUCKETS[$r->bucket] ?? $r->bucket) }})</span>@endif @if ($r->note)<div class="text-xs text-ink-500">{{ $r->note }}</div>@endif</td>
            <td class="money text-end text-sm">{{ $m($r->debit) }}</td>
            <td class="money text-end text-sm">{{ $m($r->credit) }}</td>
            <td class="money text-end text-sm">{{ $bal($r->balance) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-8 text-center text-sm text-ink-500">{{ __('No entries in this period.') }}</td></tr>
        @endforelse
      </tbody>
      <tfoot><tr class="font-semibold"><td colspan="3" class="text-end">{{ __('Closing balance on :date', ['date' => $to->format('d M Y')]) }}</td><td class="money text-end">{{ $m($s['debit']) }}</td><td class="money text-end">{{ $m($s['credit']) }}</td><td class="money text-end">{{ $bal($s['closing']) }}</td></tr></tfoot>
    </table>
  </div>
</section>
@endsection
