@extends('layouts.app')
@section('title', $voucher->number)
@section('content')
@php($m = fn ($v) => (float) $v ? \App\Support\Money::company($v) : '')
<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <a href="{{ route('accounts.day-book', ['from' => $voucher->voucher_date->toDateString(), 'to' => $voucher->voucher_date->toDateString()]) }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Day book') }}</a>
    <h1 class="mt-1 text-2xl font-semibold"><span class="font-mono">{{ $voucher->number }}</span> <span class="text-base font-normal text-ink-500">· {{ $voucher->typeLabel() }}</span>
      @if ($voucher->isCancelled())<span class="pill ms-1 bg-red-50 align-middle text-overdue">{{ __('Cancelled') }}</span>@endif</h1>
    <p class="mt-1 text-sm text-ink-700">{{ $voucher->narration }}</p>
  </div>
  <div class="flex flex-wrap gap-2">
    @if ($source)<a href="{{ $source['url'] }}" class="btn-secondary text-sm">{{ $source['label'] }}</a>@endif
    <a href="{{ route('accounts.vouchers.print', $voucher) }}" target="_blank" rel="noopener" class="btn-secondary text-sm"><x-icon name="printer" class="size-4" /> {{ __('Print to sign') }}</a>
  </div>
</div>

<section class="card grid gap-4 p-6 text-sm sm:grid-cols-4">
  <div><div class="text-xs text-ink-500">{{ __('Date in the book') }}</div><div class="font-medium">{{ $voucher->voucher_date->format('d M Y') }}</div>
    @if ($voucher->document_date)<div class="text-xs text-ink-500">{{ __('Document dated :d (books were locked)', ['d' => $voucher->document_date->format('d M Y')]) }}</div>@endif</div>
  <div><div class="text-xs text-ink-500">{{ __('Reference') }}</div><div class="font-medium">{{ $voucher->reference ?? '—' }}</div></div>
  <div><div class="text-xs text-ink-500">{{ __('Made by') }}</div><div class="font-medium">{{ $voucher->source_type ? __('The system, from the document') : ($voucher->author?->name ?? '—') }}</div></div>
  <div><div class="text-xs text-ink-500">{{ __('Posted') }}</div><div class="font-medium">{{ $voucher->created_at?->format('d M Y, H:i') }}</div></div>
</section>

<section class="card overflow-hidden">
  <table class="table">
    <thead><tr><th>{{ __('Ledger') }}</th><th>{{ __('Trip · truck') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
    <tbody>
      @foreach ($voucher->lines as $l)
        <tr>
          <td class="text-sm"><a href="{{ route('ledgers.show', $l->ledger_account_id) }}" class="font-medium hover:text-brand-700">{{ $l->ledger->name }}</a>
            <div class="text-xs text-ink-500">{{ $l->ledger->group->name }}@if ($l->bucket) · {{ __(\App\Modules\Accounts\Models\VoucherLine::BUCKETS[$l->bucket] ?? $l->bucket) }}@endif @if ($l->note) · {{ $l->note }}@endif</div></td>
          <td class="text-xs text-ink-500">@if ($l->trip)<a href="{{ route('trips.show', $l->trip_id) }}" class="font-mono hover:text-brand-700">{{ $l->trip->trip_number }}</a>@endif @if ($l->truck)<div class="font-mono">{{ $l->truck->reg_no }}</div>@endif</td>
          <td class="money text-end text-sm">{{ $m($l->debit) }}</td><td class="money text-end text-sm">{{ $m($l->credit) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot><tr class="font-semibold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="money text-end">{{ $m($voucher->total) }}</td><td class="money text-end">{{ $m($voucher->total) }}</td></tr></tfoot>
  </table>
</section>

@if ($voucher->reverses)
  <p class="text-sm">{{ __('This voucher reverses') }} <a href="{{ route('accounts.vouchers.show', $voucher->reverses) }}" class="font-mono text-brand-700 hover:underline">{{ $voucher->reverses->number }}</a>.</p>
@endif
@if ($voucher->reversedBy)
  <p class="text-sm">{{ __('Cancelled: :reason — reversed by', ['reason' => $voucher->cancel_reason]) }} <a href="{{ route('accounts.vouchers.show', $voucher->reversedBy) }}" class="font-mono text-brand-700 hover:underline">{{ $voucher->reversedBy->number }}</a>.</p>
@endif

@if (! $voucher->isCancelled() && $voucher->voucher_type !== 'reversal')
  @if ($voucher->source_type)
    <p class="text-xs text-ink-500">{{ __('Made from a document: to cancel it, void or cancel the document and the book writes the reversal.') }}</p>
  @else
    @can('accounts.post')
      <form method="POST" action="{{ route('accounts.vouchers.reverse', $voucher) }}" class="card flex flex-wrap items-end gap-3 p-4" data-confirm="{{ __('Reverse this voucher? A reversal is posted; nothing is deleted.') }}">@csrf
        <div class="min-w-64 flex-1"><label class="label" for="rv_reason">{{ __('Reason for reversing') }}</label><input id="rv_reason" name="reason" class="input" maxlength="255" required>@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
        <button class="btn-danger"><x-icon name="undo" class="size-4" /> {{ __('Reverse') }}</button>
      </form>
    @endcan
  @endif
@endif
@endsection
