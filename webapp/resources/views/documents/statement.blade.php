{{-- A ledger statement on the company's letterhead (M19): to print, save as PDF or email. --}}
@php
  $m = fn ($v) => (float) $v ? \App\Support\Money::company($v) : '';
  $bal = fn ($v) => \App\Support\Money::company(abs($v)) . ' ' . ($v > 0.0049 ? __('Dr') : ($v < -0.0049 ? __('Cr') : ''));
  $seller = $head;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Statement of account') }} — {{ $ledger->name }}</title>
@include('documents._page-style')
</head>
<body>
<div class="page">
  @include('documents._page-head', ['docTitle' => __('Statement of account'), 'docLines' => [$from->format('d M Y') . ' – ' . $to->format('d M Y')]])

  <div class="to">
    <div><div class="muted">{{ __('Account of') }}</div><strong>{{ $ledger->name }}</strong>@if ($taxId)<div class="muted">{{ $seller['tax_label'] }} {{ $taxId }}</div>@endif</div>
    <div style="text-align:right"><div class="muted">{{ __('Balance on :date', ['date' => $to->format('d M Y')]) }}</div><strong style="font-size:15px">{{ $bal($s['closing']) }}</strong><div class="muted">{{ $words }}</div></div>
  </div>

  <div class="scroll"><table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Particulars') }}</th><th>{{ __('Reference') }}</th><th class="num">{{ __('Debit') }}</th><th class="num">{{ __('Credit') }}</th><th class="num">{{ __('Balance') }}</th></tr></thead>
    <tbody>
      <tr><td>{{ $from->format('d M Y') }}</td><td><strong>{{ __('Opening balance') }}</strong></td><td></td><td></td><td></td><td class="num">{{ $bal($s['opening']) }}</td></tr>
      @foreach ($s['rows'] as $r)
        <tr>
          <td>{{ \Carbon\CarbonImmutable::parse($r->voucher_date)->format('d M Y') }}</td>
          <td>{{ $r->note ?: $r->narration }}@if ($r->status === 'cancelled') <span class="muted">({{ __('cancelled') }})</span>@endif</td>
          <td>{{ $r->reference }}</td>
          <td class="num">{{ $m($r->debit) }}</td>
          <td class="num">{{ $m($r->credit) }}</td>
          <td class="num">{{ $bal($r->balance) }}</td>
        </tr>
      @endforeach
      <tr class="sum"><td>{{ $to->format('d M Y') }}</td><td>{{ __('Closing balance') }}</td><td></td><td class="num">{{ $m($s['debit']) }}</td><td class="num">{{ $m($s['credit']) }}</td><td class="num">{{ $bal($s['closing']) }}</td></tr>
    </tbody>
  </table></div>

  <p class="foot muted">{{ __('Debit (Dr): owed to :company. Credit (Cr): owed by :company. Please tell us within 15 days if anything is different in your books.', ['company' => $seller['name']]) }}<br>{{ __('Made on :date.', ['date' => now($tz)->format('d M Y H:i')]) }}</p>
</div>
</body>
</html>
