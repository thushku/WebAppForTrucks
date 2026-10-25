@extends('layouts.app')
@section('title', $receipt->number)
@section('content')
@php($money = fn ($v) => \App\Support\Money::company($v))
<section class="card p-6">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h2 class="docno font-mono text-2xl font-semibold">{{ $receipt->number }}</h2>
      <p class="text-sm text-ink-500">{{ __('Money receipt') }} · {{ $receipt->party->name }} · {{ $receipt->receipt_date->format('d M Y') }}</p>
      @if ($receipt->isVoid())<p class="mt-2 rounded-xl bg-ink-100 px-3 py-2 text-sm">{{ __('Void: :reason', ['reason' => $receipt->void_reason]) }}</p>@endif
    </div>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
      <dt class="text-ink-500">{{ __('Received') }}</dt><dd class="money text-end font-semibold">{{ $money($receipt->amount) }}</dd>
      @if ((float) $receipt->tds_amount)<dt class="text-ink-500">{{ __('TDS') }}</dt><dd class="money text-end">{{ $money($receipt->tds_amount) }}</dd>@endif
      @if ((float) $receipt->deduction_amount)<dt class="text-ink-500">{{ __('Deductions') }}</dt><dd class="money text-end">{{ $money($receipt->deduction_amount) }}</dd>@endif
      @if ((float) $receipt->unapplied)<dt class="text-ink-500">{{ __('Kept as advance') }}</dt><dd class="money text-end">{{ $money($receipt->unapplied) }}</dd>@endif
    </dl>
  </div>
  <div class="mt-4 flex flex-wrap gap-2">
    <a href="{{ route('receipts.print', $receipt) }}" target="_blank" rel="noopener" class="btn-secondary">{{ __('Print') }}</a>
    <a href="{{ route('receipts.pdf', $receipt) }}" class="btn-secondary"><x-icon name="download" /> {{ __('PDF') }}</a>
  </div>
</section>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
  <section class="card overflow-hidden"><iframe src="{{ route('receipts.print', $receipt) }}" title="{{ __('The printed receipt') }}" class="h-[820px] w-full border-0 bg-ink-50"></iframe></section>
  <div class="grid content-start gap-6">
    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Applied to') }}</h3>
      <ul class="mt-3 space-y-1.5 text-sm">
        @forelse ($receipt->allocations as $a)<li class="flex justify-between gap-2"><a href="{{ route('invoices.show', $a->invoice) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $a->invoice->number }}</a><span class="money">{{ $money($a->total()) }}</span></li>@empty<li class="text-ink-500">{{ __('Not applied: kept as advance.') }}</li>@endforelse
      </ul>
    </section>
    @if (! $receipt->isVoid())
      @can('payment.void')
        <form method="POST" action="{{ route('receipts.void', $receipt) }}" class="card space-y-2 p-6" data-confirm="{{ __('Void this receipt? Its bills open again.') }}">@csrf
          <label class="label" for="rv_reason">{{ __('Void (cheque bounced, entered twice…)') }}</label>
          <input id="rv_reason" name="reason" class="input" maxlength="255">
          @error('reason')<span class="error">{{ $message }}</span>@enderror
          <button class="btn-danger w-full">{{ __('Void') }}</button>
        </form>
      @endcan
    @endif
  </div>
</div>
@endsection
