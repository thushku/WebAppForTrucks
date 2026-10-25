@extends('layouts.app')
@section('title', __('Receipts'))
@section('content')
@php($f = $filters)
<div class="flex flex-wrap items-center justify-between gap-3">
  <a href="{{ route('invoices.index') }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ __('Invoices') }}</a>
  @can('payment.record')<a href="{{ route('receipts.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('New receipt') }}</a>@endcan
</div>
<form method="GET" action="{{ route('receipts.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
  <div><label class="label" for="r_party">{{ __('Client') }}</label><select id="r_party" name="party" class="input w-52"><option value="">{{ __('Any') }}</option>@foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((int) ($f['party'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></div>
  <div><label class="label" for="r_from">{{ __('From date') }}</label><input id="r_from" type="date" name="from" value="{{ $f['from'] ?? '' }}" class="input"></div>
  <div><label class="label" for="r_to">{{ __('To date') }}</label><input id="r_to" type="date" name="to" value="{{ $f['to'] ?? '' }}" class="input"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
</form>
<div class="flex flex-wrap gap-x-6 gap-y-1 rounded-2xl bg-brand-50 px-5 py-3 text-sm text-brand-900">
  <span><strong>{{ $totals->n }}</strong> {{ trans_choice('receipt|receipts', $totals->n) }}</span>
  <span>{{ __('Received') }} <strong class="money">{{ \App\Support\Money::company($totals->amount) }}</strong></span>
  <span>{{ __('TDS deducted by clients') }} <strong class="money">{{ \App\Support\Money::company($totals->tds) }}</strong></span>
</div>
<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Client') }}</th><th>{{ __('Date') }}</th><th>{{ __('Mode') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('TDS') }}</th><th class="text-end">{{ __('Advance') }}</th></tr></thead>
      <tbody>
        @forelse ($receipts as $r)
          <tr class="{{ $r->isVoid() ? 'line-through opacity-60' : '' }}">
            <td><a href="{{ route('receipts.show', $r) }}" class="docno font-mono text-sm font-semibold hover:text-brand-700">{{ $r->number }}</a></td>
            <td class="text-sm">{{ $r->party->name }}</td><td class="text-sm">{{ $r->receipt_date->format('d M Y') }}</td>
            <td class="text-sm">{{ __(\App\Modules\Invoicing\Models\Receipt::MODES[$r->mode]) }}<div class="text-xs text-ink-500">{{ $r->reference }}</div></td>
            <td class="money text-end">{{ \App\Support\Money::company($r->amount) }}</td><td class="money text-end">{{ (float) $r->tds_amount ? \App\Support\Money::company($r->tds_amount) : '—' }}</td>
            <td class="money text-end">{{ (float) $r->unapplied ? \App\Support\Money::company($r->unapplied) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ __('No receipts yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
{{ $receipts->links() }}
@endsection
