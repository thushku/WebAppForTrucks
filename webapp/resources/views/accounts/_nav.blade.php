{{-- The book's pages, as tabs (Accounts). --}}
@php
  $tabs = [
    'accounts.index' => __('Overview'), 'accounts.day-book' => __('Day book'), 'accounts.trial-balance' => __('Trial balance'),
    'accounts.profit-loss' => __('Profit and loss'), 'accounts.balance-sheet' => __('Balance sheet'), 'accounts.trucks' => __('Truck profit'),
    'accounts.ageing' => __('Ageing'), 'accounts.tax' => __('Tax returns'), 'accounts.tds' => __('TDS'), 'accounts.bank' => __('Bank reconciliation'), 'accounts.chart' => __('Chart of accounts'),
  ];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <nav class="flex flex-wrap gap-1.5" aria-label="{{ __('Accounts') }}">
    @foreach ($tabs as $route => $label)
      @php($on = request()->routeIs($route))
      <a href="{{ route($route, array_filter(['from' => request('from'), 'to' => request('to')])) }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $on ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}" @if ($on) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </nav>
  <div class="flex flex-wrap gap-2">
    @can('accounts.lock')<a href="{{ route('accounts.lock') }}" class="btn-secondary text-sm"><x-icon name="lock" class="size-4" /> {{ __('Lock') }}</a>@endcan
    @can('accounts.post')
      <a href="{{ route('accounts.opening') }}" class="btn-secondary text-sm"><x-icon name="ledger" class="size-4" /> {{ __('Opening balances') }}</a>
      <a href="{{ route('accounts.payments.create') }}" class="btn-secondary text-sm"><x-icon name="wallet" class="size-4" /> {{ __('Payment') }}</a>
      <a href="{{ route('accounts.journal.create') }}" class="btn-primary text-sm"><x-icon name="plus" class="size-4" /> {{ __('Journal') }}</a>
    @endcan
  </div>
</div>
