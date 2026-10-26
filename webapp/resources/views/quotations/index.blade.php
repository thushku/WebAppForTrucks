@extends('layouts.app')
@section('title', __('Quotations'))
@section('content')
@php
  $tones = ['draft' => 'bg-ink-100 text-ink-700', 'issued' => 'bg-brand-50 text-brand-700', 'accepted' => 'bg-emerald-50 text-paid', 'declined' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-500'];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Status') }}">
    @foreach (\App\Http\Controllers\Invoicing\QuotationController::TABS as $key => $label)
      <a href="{{ route('quotations.index', array_filter(['tab' => $key, 'q' => $q])) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }}</a>
    @endforeach
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('invoices.index') }}" class="btn-secondary">{{ __('Bills') }}</a>
    @can('invoice.create')<a href="{{ route('quotations.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('New quotation') }}</a>@endcan
  </div>
</div>

<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div><label class="label" for="q_search">{{ __('Number or client') }}</label><input id="q_search" name="q" value="{{ $q }}" class="input w-56" maxlength="60"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
</form>

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Client') }}</th><th>{{ __('Date') }}</th><th>{{ __('Valid until') }}</th><th class="text-end">{{ __('Routes') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
      @forelse ($quotations as $row)
        <tr>
          <td><a href="{{ route('quotations.show', $row) }}" class="font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $row->label }}</a></td>
          <td>{{ $row->party->name }}</td>
          <td class="nowrap text-sm">{{ $row->quote_date->format('d M Y') }}</td>
          <td class="nowrap text-sm">{{ $row->valid_until?->format('d M Y') ?? '—' }}</td>
          <td class="text-end">{{ $row->lines_count }}</td>
          <td><span class="pill {{ $row->isExpired($today) ? 'bg-amber-50 text-partial' : ($tones[$row->status] ?? '') }}">{{ $row->statusLabel($today) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6" class="py-8 text-center text-sm text-ink-500">{{ __('No quotations here. A quotation gives a client your rates for their routes; once accepted it becomes their rate contract.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
{{ $quotations->links() }}
@endsection
