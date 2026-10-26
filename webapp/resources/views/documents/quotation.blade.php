{{-- A quotation / rate confirmation on the letterhead (M21): to print, save as PDF or email. --}}
@php
  $q = $quotation;
  $money = fn ($v) => $v !== null ? \App\Support\Money::company($v) : '—';
  $bases = \App\Modules\Parties\Models\RateContractLine::BASES;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Quotation') }} {{ $q->number }} — {{ $q->party->name }}</title>
@include('documents._page-style')
</head>
<body>
<div class="page">
  @include('documents._page-head', ['docTitle' => __('Quotation'), 'docLines' => array_filter([
    $q->number ?: __('Draft — not sent'), __('Date: :date', ['date' => $q->quote_date->format('d M Y')]),
    $q->valid_until ? __('Valid until :date', ['date' => $q->valid_until->format('d M Y')]) : null])])

  <div class="to">
    <div>
      <div class="muted">{{ __('To') }}</div>
      <strong>{{ $q->party->legal_name ?: $q->party->name }}</strong>
      @foreach ($partyAddress as $line)<div class="muted">{{ $line }}</div>@endforeach
      @if ($taxId)<div class="muted">{{ $seller['tax_label'] }} {{ $taxId }}</div>@endif
    </div>
    @if ($q->subject)<div style="text-align:right; max-width:50%"><div class="muted">{{ __('Subject') }}</div><strong>{{ $q->subject }}</strong></div>@endif
  </div>

  <p>{{ __('We are pleased to offer the following rates for your loads:') }}</p>
  <table>
    <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Material') }}</th><th>{{ __('Truck') }}</th><th class="num">{{ __('Rate') }}</th><th class="num">{{ __('Min. weight') }}</th><th>{{ __('Detention') }}</th><th class="num">{{ __('Loading / unloading') }}</th></tr></thead>
    <tbody>
      @foreach ($q->lines as $l)
        <tr>
          <td><strong>{{ $l->from->name }} → {{ $l->to->name }}</strong>@if ($l->note)<div class="muted">{{ $l->note }}</div>@endif</td>
          <td>{{ $l->material ? ($materials[$l->material] ?? $l->material) : __('Any') }}</td>
          <td>{{ $l->truck_type ? ($truckTypes[$l->truck_type] ?? $l->truck_type) : __('Any') }}</td>
          <td class="num"><strong>{{ $money($l->rate) }}</strong><div class="muted">{{ __($bases[$l->basis] ?? $l->basis) }}</div></td>
          <td class="num">{{ $l->min_weight_mt !== null ? rtrim(rtrim(number_format((float) $l->min_weight_mt, 3), '0'), '.') . ' MT' : '—' }}</td>
          <td>{{ $l->detention_rate_per_day !== null ? __(':amount a day after :h free hours', ['amount' => $money($l->detention_rate_per_day), 'h' => $l->detention_free_hours ?? 0]) : '—' }}</td>
          <td class="num">{{ $l->loading_charge || $l->unloading_charge ? $money($l->loading_charge) . ' / ' . $money($l->unloading_charge) : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <p class="foot">{{ $taxNote }}</p>
  @if ($q->terms)<div class="foot"><strong>{{ __('Terms') }}</strong><div style="white-space:pre-line">{{ $q->terms }}</div></div>@endif

  <div class="signs">
    <div>{{ __('Accepted by the client — signature, name and date') }}</div>
    <div style="text-align:right">{{ __('For :company — authorised signatory', ['company' => $seller['legal_name'] ?: $seller['name']]) }}</div>
  </div>
  <p class="foot muted">{{ __('Made on :date.', ['date' => now($tz)->format('d M Y H:i')]) }}</p>
</div>
</body>
</html>
