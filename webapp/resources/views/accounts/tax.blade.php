@extends('layouts.app')
@section('title', __('Tax returns'))
@use('App\Http\Controllers\Accounts\ReportsController')
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
@include('accounts._nav')
<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
  @if ($registrations->count() > 1)
    <div><label class="label" for="tx_reg">{{ $india ? 'GSTIN' : 'TRN' }}</label><select id="tx_reg" name="registration" class="input">@foreach ($registrations as $r)<option value="{{ $r->id }}" @selected($registration?->id === $r->id)>{{ $r->tax_id }}{{ $r->state_code ? ' · ' . \App\Support\Regions::name($r->country, $r->state_code) : '' }}</option>@endforeach</select></div>
  @endif
  <div><label class="label" for="tx_month">{{ __('Month') }}</label><input id="tx_month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="input"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <p class="ms-auto max-w-md text-xs text-ink-500">{{ __('Prepared from the bills as issued. Your accountant checks and files the return; nothing is sent to the tax portal from here.') }}</p>
</form>

@if (! $registration)
  <p class="rounded-2xl bg-ink-50 px-5 py-4 text-sm">{{ __('No tax registration yet. Add your GSTIN or TRN in Settings → Tax.') }}</p>
@elseif ($india)
  @php($s = $gstr1['summary'])
  <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat"><div class="stat-label">{{ __('Taxable value (tax charged)') }}</div><div class="stat-value money">{{ $m($s['taxable']) }}</div><div class="mt-1 text-xs text-ink-500">IGST {{ $m($s['igst']) }} · CGST {{ $m($s['cgst']) }} · SGST {{ $m($s['sgst']) }}</div></div>
    <div class="stat"><div class="stat-label">{{ __('Reverse charge (the client pays)') }}</div><div class="stat-value money">{{ $m($s['rcm_taxable']) }}</div><div class="mt-1 text-xs text-ink-500">{{ __('Tax for the recipient: :t', ['t' => $m($s['rcm_tax'])]) }}</div></div>
    <div class="stat"><div class="stat-label">{{ __('Exempt (bills of supply)') }}</div><div class="stat-value money">{{ $m($s['exempt']) }}</div></div>
    <div class="stat"><div class="stat-label">{{ __('Documents') }}</div><div class="stat-value">{{ $s['documents'] }}</div><div class="mt-1 text-xs text-ink-500">{{ trans_choice(':count cancelled|:count cancelled', $s['cancelled']) }}</div></div>
  </div>
  <section class="card overflow-hidden">
    <h2 class="px-6 pt-6 text-lg font-semibold">{{ __('GSTR-1 for :gstin, :month', ['gstin' => $registration->tax_id, 'month' => $month->translatedFormat('F Y')]) }}</h2>
    <p class="px-6 text-sm text-ink-500">{{ __('Each table downloads with the columns of the GST offline tool’s sheet, ready to paste (check the date format against its sample).') }}</p>
    <table class="table mt-3">
      <thead><tr><th>{{ __('Table') }}</th><th class="text-end">{{ __('Rows') }}</th><th class="text-end">{{ __('Taxable value') }}</th><th></th></tr></thead>
      <tbody>
        @foreach (['b2b' => __('4A, 4B — B2B invoices (registered clients; RCM marked)'), 'b2cl' => __('5 — B2C large (other state, above ₹1 lakh)'), 'b2cs' => __('7 — B2C small'), 'cdnr' => __('9B — Credit / debit notes (registered)'), 'cdnur' => __('9B — Credit / debit notes (unregistered)'), 'exemp' => __('8 — Nil rated, exempt, non-GST'), 'hsn' => __('12 — HSN / SAC summary'), 'docs' => __('13 — Documents issued')] as $key => $label)
          <tr>
            <td class="text-sm">{{ $label }}</td>
            <td class="text-end text-sm">{{ count($gstr1[$key]) }}</td>
            <td class="money text-end text-sm">{{ in_array($key, ['docs', 'exemp'], true) ? ($key === 'exemp' ? $m($gstr1['exemp'][0]['exempt']) : '') : $m(collect($gstr1[$key])->sum('taxable')) }}</td>
            <td class="text-end">@if (count($gstr1[$key]))<a href="{{ route('accounts.tax', ['registration' => $registration->id, 'month' => $month->format('Y-m'), 'section' => $key]) }}" class="btn-ghost text-xs"><x-icon name="download" class="size-4" /> CSV</a>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
  @if ($gstr1['b2b'])
    <section class="card overflow-hidden">
      <h3 class="px-6 pt-6 font-semibold">{{ __('B2B invoices') }}</h3>
      <div class="overflow-x-auto">
        <table class="table mt-2">
          <thead><tr><th>{{ __('Client GSTIN') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Place of supply') }}</th><th>RCM</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Taxable') }}</th><th class="text-end">{{ __('Invoice value') }}</th></tr></thead>
          <tbody>@foreach ($gstr1['b2b'] as $r)<tr><td class="font-mono text-xs">{{ $r['gstin'] }}<div class="font-sans text-ink-500">{{ $r['name'] }}</div></td><td class="font-mono text-xs">{{ $r['number'] }}<div class="text-ink-500">{{ $r['date'] }}</div></td><td class="text-sm">{{ $r['pos'] }}</td><td class="text-sm">{{ $r['rcm'] }}</td><td class="text-end text-sm">{{ $r['rate'] }}%</td><td class="money text-end text-sm">{{ $m($r['taxable']) }}</td><td class="money text-end text-sm">{{ $m($r['value']) }}</td></tr>@endforeach</tbody>
        </table>
      </div>
    </section>
  @endif
  <p class="text-xs text-ink-500">{{ __('GSTR-3B working: the tax charged above goes in 3.1(a); supplies where the recipient pays under reverse charge are reported in GSTR-1 (4B) and not paid by you; exempt supplies in 3.1(c). Input tax credit comes with purchases. Check with your CA.') }}</p>
@else
  <section class="card overflow-hidden">
    <h2 class="px-6 pt-6 text-lg font-semibold">{{ __('VAT 201 for :trn, :month', ['trn' => $registration->tax_id, 'month' => $month->translatedFormat('F Y')]) }}</h2>
    <table class="table mt-3">
      <thead><tr><th>{{ __('Box') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('VAT') }}</th></tr></thead>
      <tbody>
        @foreach (array_values($vat['emirates']) as $i => $b)<tr><td class="text-sm">1{{ chr(97 + $i) }} · {{ __('Standard-rated supplies in :e', ['e' => $b['emirate']]) }}</td><td class="money text-end text-sm">{{ $m($b['amount']) }}</td><td class="money text-end text-sm">{{ $m($b['vat']) }}</td></tr>@endforeach
        <tr><td class="text-sm">4 · {{ __('Zero-rated supplies') }}</td><td class="money text-end text-sm">{{ $m($vat['zero_rated']) }}</td><td></td></tr>
        <tr><td class="text-sm">5 · {{ __('Exempt supplies') }}</td><td class="money text-end text-sm">{{ $m($vat['exempt']) }}</td><td></td></tr>
      </tbody>
      <tfoot><tr class="font-semibold"><td>8 · {{ __('Totals') }}</td><td class="money text-end">{{ $m($vat['total_amount']) }}</td><td class="money text-end">{{ $m($vat['total_vat']) }}</td></tr></tfoot>
    </table>
  </section>
  <p class="text-xs text-ink-500">{{ __('Output side only; input VAT (boxes 9–11) comes with purchases. Check with your tax agent before filing.') }}</p>
@endif
@endsection
