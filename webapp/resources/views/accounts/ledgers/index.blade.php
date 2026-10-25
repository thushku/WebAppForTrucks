@extends('layouts.app')
@section('title', __('Ledgers'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Ledgers') }}">
    @foreach (\App\Http\Controllers\Accounts\LedgerController::TABS as $key => $label)
      <a href="{{ route('ledgers.index', array_filter(['tab' => $key, 'q' => $filters['q'] ?? null])) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }}<span class="ms-1 text-xs text-ink-400">{{ $counts[$key] }}</span></a>
    @endforeach
  </div>
  <form method="GET" class="flex gap-2"><input type="hidden" name="tab" value="{{ $tab }}"><input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56" placeholder="{{ __('Name') }}" aria-label="{{ __('Search by name') }}"><button class="btn-secondary"><x-icon name="search" /></button></form>
</div>
<p class="text-sm text-ink-500">{{ match ($tab) { 'clients' => __('Clients who pay you (the billing party on the LR).'), 'suppliers' => __('Supplier = owner or broker of a hired truck. Vendor = sells you goods or services (pump, workshop, tyres…).'), default => __('Each crew member’s account: trip cash given and spent, bata and earnings.') } }}</p>

<div class="grid gap-4 sm:grid-cols-3">
  <div class="stat"><div class="stat-label">{{ __('Clients will give you') }}</div><div class="stat-value money">{{ $m($receivable) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('You will give suppliers and vendors') }}</div><div class="stat-value money">{{ $m($payable) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Company cash with crew') }}</div><div class="stat-value money">{{ $m($withCrew) }}</div></div>
</div>

<section class="card overflow-hidden">
  <table class="table">
    <thead><tr><th>{{ __('Name') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th>{{ __('Balance') }}</th></tr></thead>
    <tbody>
      @forelse ($rows as $l)
        <tr>
          <td><a href="{{ route('ledgers.show', $l->id) }}" class="font-medium hover:text-brand-700">{{ $l->name }}</a><div class="text-xs text-ink-500">{{ $l->group }}</div></td>
          <td class="money text-end text-sm">{{ $m($l->debit) }}</td>
          <td class="money text-end text-sm">{{ $m($l->credit) }}</td>
          <td class="text-sm font-medium {{ $l->balance > 0.004 ? 'text-emerald-700' : ($l->balance < -0.004 ? 'text-overdue' : 'text-ink-500') }}">{{ \App\Http\Controllers\Accounts\LedgerController::words($l->kind, $l->name, $l->balance) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="py-10 text-center text-ink-500">{{ __('No entries yet. Ledgers fill by themselves from bills, receipts, advances, road entries and hire challans.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
@endsection
