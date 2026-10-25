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
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font: 12px/1.45 "Noto Sans", Arial, sans-serif; color: #0f1a2b; background: #f5f7fa; }
  .page { max-width: 210mm; margin: 16px auto; background: #fff; padding: 14mm 12mm; }
  .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #162f5e; padding-bottom: 10px; }
  .head h1 { margin: 0; font-size: 18px; }
  .muted { color: #5b6b80; }
  .title { text-align: right; }
  .title h2 { margin: 0; font-size: 16px; letter-spacing: .02em; }
  .to { margin: 14px 0; display: flex; justify-content: space-between; gap: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #5b6b80; border-bottom: 1px solid #c8d1dc; padding: 6px 4px; }
  td { border-bottom: 1px solid #e4e9f0; padding: 5px 4px; vertical-align: top; }
  td:first-child { white-space: nowrap; }
  .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .sum td { font-weight: 700; border-top: 2px solid #162f5e; }
  .foot { margin-top: 18px; font-size: 11px; }
  @media print { body { background: #fff; } .page { margin: 0; max-width: none; padding: 0; } @page { size: A4; margin: 12mm; } }
</style>
</head>
<body>
<div class="page">
  <div class="head">
    <div>
      @if (! empty($seller['logo']))<img src="{{ $seller['logo'] }}" alt="" style="max-height:48px; display:block; margin-bottom:6px">@endif
      <h1>{{ $seller['legal_name'] ?: $seller['name'] }}</h1>
      <div class="muted">{{ implode(', ', $seller['address']) }}</div>
      @if ($seller['tax_id'])<div class="muted">{{ $seller['tax_label'] }}: {{ $seller['tax_id'] }}</div>@endif
      @if ($seller['phone'] || $seller['email'])<div class="muted">{{ collect([$seller['phone'], $seller['email']])->filter()->join(' · ') }}</div>@endif
    </div>
    <div class="title">
      <h2>{{ __('Statement of account') }}</h2>
      <div class="muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    </div>
  </div>

  <div class="to">
    <div><div class="muted">{{ __('Account of') }}</div><strong>{{ $ledger->name }}</strong>@if ($taxId)<div class="muted">{{ $seller['tax_label'] }} {{ $taxId }}</div>@endif</div>
    <div style="text-align:right"><div class="muted">{{ __('Balance on :date', ['date' => $to->format('d M Y')]) }}</div><strong style="font-size:15px">{{ $bal($s['closing']) }}</strong><div class="muted">{{ $words }}</div></div>
  </div>

  <table>
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
  </table>

  <p class="foot muted">{{ __('Debit (Dr): owed to :company. Credit (Cr): owed by :company. Please tell us within 15 days if anything is different in your books.', ['company' => $seller['name']]) }}<br>{{ __('Made on :date.', ['date' => now($tz)->format('d M Y H:i')]) }}</p>
</div>
</body>
</html>
