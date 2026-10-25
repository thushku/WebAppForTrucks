@php
  $d = $doc;
  $s = $d['seller'];
  $paper = 'A5';
  $pageTitle = __('Receipt') . ' ' . $d['number'];
@endphp
@include('documents._frame-open')
<div class="sheet a5">
  @if ($d['is_void'])<div class="watermark">{{ __('VOID') }}</div>@endif
  <table class="box">
    <tr>
      <td>
        <div class="strong" style="font-size:12pt">{{ $s['name'] }}</div>
        @foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
        @if ($s['tax_id'])<div class="small">{{ $s['tax_label'] }} <span class="num">{{ $s['tax_id'] }}</span></div>@endif
      </td>
      <td class="r" style="width:40%"><h1 style="font-size:12pt">{{ __('Money receipt') }}</h1><div class="num strong">{{ $d['number'] }}</div><div class="num small">{{ $d['date'] }}</div></td>
    </tr>
  </table>
  <table class="box small" style="border-top:0">
    <tr><td>{{ __('Received with thanks from') }} <span class="strong">{{ $d['buyer']['name'] }}</span>@if ($d['buyer']['tax_id']) ({{ $d['buyer']['tax_id'] }})@endif</td></tr>
    <tr><td>{{ __('the sum of') }} <span class="strong">{{ $d['amount'] }}</span> — {{ $d['words'] }}</td></tr>
    <tr><td>{{ __('by') }} {{ $d['mode'] }}</td></tr>
    @if ($d['tds'])<tr><td>{{ __('TDS deducted by you') }}: <span class="strong">{{ $d['tds'] }}</span></td></tr>@endif
    @if ($d['deduction'])<tr><td>{{ __('Deductions') }}: <span class="strong">{{ $d['deduction'] }}</span></td></tr>@endif
    @if ($d['tds'] || $d['deduction'])<tr><td>{{ __('Total settled') }}: <span class="strong">{{ $d['gross'] }}</span></td></tr>@endif
    <tr><td class="muted">{{ $d['narration'] }}</td></tr>
  </table>
  @if ($d['applied'])
    <table class="grid small" style="margin-top:6px">
      <thead><tr><th>{{ __('Against bill') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Settled') }}</th></tr></thead>
      <tbody>@foreach ($d['applied'] as $a)<tr><td class="num">{{ $a['number'] }}</td><td class="num">{{ $a['date'] }}</td><td class="r num">{{ $a['amount'] }}@if ($a['tds'])<div class="muted">{{ __('incl. TDS :t', ['t' => $a['tds']]) }}</div>@endif</td></tr>@endforeach</tbody>
    </table>
  @endif
  @if ($d['unapplied'])<p class="small">{{ __('Kept as advance: :amount', ['amount' => $d['unapplied']]) }}</p>@endif
  @if ($d['cheque'])<p class="small muted">{{ __('Subject to realisation of the cheque.') }}</p>@endif
  <div class="r small" style="margin-top:14mm">{{ __('For :name', ['name' => $s['name']]) }}<br><span class="strong">{{ __('Authorised signatory') }}</span></div>
</div>
@include('documents._frame-close')
