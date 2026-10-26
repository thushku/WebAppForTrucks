{{-- An accounting voucher to print and sign (M21, blueprint 10 §6.3): payment, receipt, journal, contra. --}}
@php
  $v = $voucher;
  $m = fn ($x) => (float) $x ? \App\Support\Money::company($x) : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $docTitle }} {{ $v->number }}</title>
@include('documents._page-style')
</head>
<body>
<div class="page">
  @include('documents._page-head', ['docTitle' => $docTitle, 'docLines' => array_filter([$v->number, __('Date: :date', ['date' => ($v->document_date ?? $v->voucher_date)->format('d M Y')]),
    $v->isCancelled() ? __('Cancelled: :reason', ['reason' => $v->cancel_reason]) : null])])

  <div class="to">
    <div>
      @if ($party)<div class="muted">{{ $v->voucher_type === 'receipt' ? __('Received from') : __('Paid to') }}</div><strong style="font-size:15px">{{ $party }}</strong>@endif
      <div style="margin-top:6px">{{ $v->narration }}</div>
      @if ($v->reference)<div class="muted">{{ __('Reference / UTR / cheque: :ref', ['ref' => $v->reference]) }}</div>@endif
      @if ($through)<div class="muted">{{ __('Through: :ledger', ['ledger' => $through]) }}</div>@endif
    </div>
    <div style="text-align:right"><div class="muted">{{ __('Amount') }}</div><strong style="font-size:18px">{{ \App\Support\Money::company($amount) }}</strong><div class="muted">{{ $words }}</div></div>
  </div>

  <table>
    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Trip / truck') }}</th><th class="num">{{ __('Debit') }}</th><th class="num">{{ __('Credit') }}</th></tr></thead>
    <tbody>
      @foreach ($v->lines as $l)
        <tr>
          <td>{{ $l->ledger->name }}@if ($l->note)<div class="muted">{{ $l->note }}</div>@endif</td>
          <td>{{ collect([$l->trip?->trip_number, $l->truck?->reg_no])->filter()->join(' · ') }}</td>
          <td class="num">{{ $m($l->debit) }}</td>
          <td class="num">{{ $m($l->credit) }}</td>
        </tr>
      @endforeach
      <tr class="sum"><td colspan="2">{{ __('Total') }}</td><td class="num">{{ $m($v->lines->sum('debit')) }}</td><td class="num">{{ $m($v->lines->sum('credit')) }}</td></tr>
    </tbody>
  </table>

  <div class="signs">
    <div>{{ __('Prepared by') }}@if ($v->author)<br><strong style="color:#0f1a2b">{{ $v->author->name }}</strong>@endif</div>
    <div>{{ __('Approved by') }}</div>
    <div>{{ ['receipt' => __('Paid by — signature'), 'payment' => __('Received by — signature')][$v->voucher_type] ?? __('Checked by') }}</div>
  </div>
  <p class="foot muted">{{ __('Printed on :date.', ['date' => now($tz)->format('d M Y H:i')]) }}</p>
</div>
</body>
</html>
