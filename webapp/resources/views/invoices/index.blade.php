@extends('layouts.app')
@section('title', __('Invoices'))
@section('content')
@php($f = $filters)
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Status') }}">
    @foreach (\App\Http\Controllers\Invoicing\InvoiceController::TABS as $key => $label)
      <a href="{{ route('invoices.index', array_filter(['tab' => $key] + collect($f)->except('tab')->all())) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }}</a>
    @endforeach
  </div>
  <div class="flex flex-wrap gap-2">
    @can('payment.view')<a href="{{ route('receipts.index') }}" class="btn-secondary">{{ __('Receipts') }}</a>@endcan
    <a href="{{ route('quotations.index') }}" class="btn-secondary">{{ __('Quotations') }}</a>
    @can('lr.view')<a href="{{ route('lr-books.index') }}" class="btn-secondary">{{ __('LR books') }}</a>@endcan
    @can('invoice.create')<a href="{{ route('invoices.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('New bill') }}@if ($unbilled)<span class="ms-1 rounded-full bg-white/20 px-1.5 text-xs">{{ __(':n LRs waiting', ['n' => $unbilled]) }}</span>@endif</a>@endcan
  </div>
</div>

<form method="GET" action="{{ route('invoices.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div><label class="label" for="i_party">{{ __('Client') }}</label><select id="i_party" name="party" class="input w-52"><option value="">{{ __('Any') }}</option>@foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((int) ($f['party'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></div>
  <div><label class="label" for="i_from">{{ __('From date') }}</label><input id="i_from" type="date" name="from" value="{{ $f['from'] ?? '' }}" class="input"></div>
  <div><label class="label" for="i_to">{{ __('To date') }}</label><input id="i_to" type="date" name="to" value="{{ $f['to'] ?? '' }}" class="input"></div>
  <div><label class="label" for="i_q">{{ __('Number') }}</label><input id="i_q" name="q" value="{{ $f['q'] ?? '' }}" class="input w-40" maxlength="40"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <a href="{{ route('invoices.index') }}" class="btn-ghost text-sm">{{ __('Reset filters') }}</a>
</form>

<div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-2xl bg-brand-50 px-5 py-3 text-sm text-brand-900">
  <span><strong>{{ $totals->n }}</strong> {{ trans_choice('document|documents', $totals->n) }}</span>
  <span>{{ __('Billed') }} <strong class="money">{{ \App\Support\Money::company($totals->total) }}</strong></span>
  <span>{{ __('Still to come') }} <strong class="money">{{ \App\Support\Money::company($totals->due) }}</strong></span>
</div>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Client') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @forelse ($invoices as $invoice)
          <tr class="{{ $invoice->isVoid() ? 'opacity-60' : '' }}">
            <td>
              <a href="{{ $invoice->isDraft() ? route('invoices.edit', $invoice) : route('invoices.show', $invoice) }}" class="docno font-mono text-sm font-semibold text-ink-900 hover:text-brand-700">{{ $invoice->numberOrDraft() }}</a>
              <div class="text-xs text-ink-500">{{ $invoice->kindLabel() }}{{ $invoice->reverse_charge ? ' · ' . __('RCM') : '' }}</div>
            </td>
            <td class="text-sm">{{ $invoice->party->name }}</td>
            <td class="text-sm">{{ $invoice->issue_date->format('d M Y') }}</td>
            <td class="text-sm">{{ $invoice->isCreditNote() ? '—' : $invoice->due_date->format('d M Y') }}</td>
            <td class="money text-end">{{ $invoice->isCreditNote() ? '− ' : '' }}{{ \App\Support\Money::company($invoice->total) }}</td>
            <td class="money text-end">{{ $invoice->isCreditNote() || $invoice->isVoid() ? '—' : \App\Support\Money::company($invoice->balance_due) }}</td>
            <td>@include('invoices._status')</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ $tab === 'draft' ? __('No drafts.') : __('No bills here yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
{{ $invoices->links() }}
@endsection
