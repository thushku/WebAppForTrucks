@use('App\Modules\Invoicing\Design\DesignSchema')
@php
  $d = $doc;
  $s = $d['seller'];
  $design = $d['design'];
  $f = $design['fields'];
  $L = fn (string $key) => DesignSchema::label($design, $key);
  $pageTitle = __('Receipt') . ' ' . $d['number'];
@endphp
@include('documents._frame-open')
@if ($design['theme'] === 'thermal_80')
{{-- Thermal 80 mm: one narrow column for a roll printer. --}}
<div class="sheet">
  @if ($d['is_void'])<div class="watermark" style="font-size:28pt">{{ __('VOID') }}</div>@endif
  <div class="c">
    @if ($f['logo'] && $s['logo'])<img class="logo" src="{{ $s['logo'] }}" alt="">@endif
    <div class="strong mid">{{ $s['name'] }}</div>
    @if ($f['address'])@foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach @endif
    @if ($f['tax_registration'] && $s['tax_id'])<div class="small">{{ $s['tax_label'] }} {{ $s['tax_id'] }}</div>@endif
    @foreach ($design['custom'] as $c)<div class="small">{{ trim($c['label'] . ' ' . $c['value']) }}</div>@endforeach
    <div class="rule"></div>
    <h1>{{ DesignSchema::title($design, 'receipt') }}</h1>
    <div class="num">{{ $d['number'] }} · {{ $d['date'] }}</div>
  </div>
  <div class="rule"></div>
  <div class="small">{{ $L('received_from') }}</div>
  <div class="strong">{{ $d['buyer']['name'] }}</div>
  <div class="rule"></div>
  <table class="small">
    <tr><td style="padding:1px 0">{{ __('Amount') }}</td><td class="r num strong" style="padding:1px 0">{{ $d['amount'] }}</td></tr>
    @if ($d['tds'])<tr><td style="padding:1px 0">{{ __('TDS') }}</td><td class="r num" style="padding:1px 0">{{ $d['tds'] }}</td></tr>@endif
    @if ($d['deduction'])<tr><td style="padding:1px 0">{{ __('Deductions') }}</td><td class="r num" style="padding:1px 0">{{ $d['deduction'] }}</td></tr>@endif
    @if ($d['tds'] || $d['deduction'])<tr><td style="padding:1px 0">{{ __('Total settled') }}</td><td class="r num strong" style="padding:1px 0">{{ $d['gross'] }}</td></tr>@endif
  </table>
  @if ($f['words'])<div class="small">{{ $d['words'] }}</div>@endif
  <div class="small">{{ __('by') }} {{ $d['mode'] }}</div>
  @if ($f['applied'] && $d['applied'])
    <div class="rule"></div>
    @foreach ($d['applied'] as $a)<div class="small split"><span class="num">{{ $a['number'] }}</span><span class="r num">{{ $a['amount'] }}</span></div>@endforeach
  @endif
  @if ($d['unapplied'])<div class="small">{{ __('Kept as advance: :amount', ['amount' => $d['unapplied']]) }}</div>@endif
  @if ($d['cheque'])<div class="small">{{ __('Subject to realisation of the cheque.') }}</div>@endif
  @if ($f['signature'])<div class="rule"></div><div class="c small" style="margin-top:10mm">{{ $L('signatory') }}</div>@endif
  @if ($design['footer'])<div class="rule"></div><p class="small footer">{{ $design['footer'] }}</p>@endif
</div>
@else
<div class="sheet">
  @if ($d['is_void'])<div class="watermark">{{ __('VOID') }}</div>@endif
  <table class="box head">
    <tr>
      <td>
        <div class="split" style="align-items:flex-start">
          @if ($f['logo'] && $s['logo'])<div style="flex:0 0 auto"><img class="logo" src="{{ $s['logo'] }}" alt=""></div>@endif
          <div>
            <div class="strong mid">{{ $s['name'] }}</div>
            @if ($f['address'])@foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach @endif
            @if ($f['tax_registration'] && $s['tax_id'])<div class="small">{{ $s['tax_label'] }} <span class="num">{{ $s['tax_id'] }}</span></div>@endif
            @foreach ($design['custom'] as $c)<div class="small">@if ($c['label'])<span class="strong">{{ $c['label'] }}</span> @endif{{ $c['value'] }}</div>@endforeach
          </div>
        </div>
      </td>
      <td class="r" style="width:40%"><h1 class="mid">{{ DesignSchema::title($design, 'receipt') }}</h1><div class="num strong">{{ $d['number'] }}</div><div class="num small">{{ $d['date'] }}</div></td>
    </tr>
  </table>
  <table class="box small" style="border-top:0">
    <tr><td>{{ $L('received_from') }} <span class="strong">{{ $d['buyer']['name'] }}</span>@if ($d['buyer']['tax_id']) ({{ $d['buyer']['tax_id'] }})@endif</td></tr>
    <tr><td>{{ __('the sum of') }} <span class="strong">{{ $d['amount'] }}</span>@if ($f['words']) — {{ $d['words'] }}@endif</td></tr>
    <tr><td>{{ __('by') }} {{ $d['mode'] }}</td></tr>
    @if ($d['tds'])<tr><td>{{ __('TDS deducted by you') }}: <span class="strong">{{ $d['tds'] }}</span></td></tr>@endif
    @if ($d['deduction'])<tr><td>{{ __('Deductions') }}: <span class="strong">{{ $d['deduction'] }}</span></td></tr>@endif
    @if ($d['tds'] || $d['deduction'])<tr><td>{{ __('Total settled') }}: <span class="strong">{{ $d['gross'] }}</span></td></tr>@endif
    @if ($d['narration'])<tr><td class="muted">{{ $d['narration'] }}</td></tr>@endif
  </table>
  @if ($f['applied'] && $d['applied'])
    <table class="grid small" style="margin-top:6px">
      <thead><tr><th>{{ __('Against bill') }}</th><th>{{ __('Date') }}</th><th class="r">{{ __('Settled') }}</th></tr></thead>
      <tbody>@foreach ($d['applied'] as $a)<tr><td class="num">{{ $a['number'] }}</td><td class="num">{{ $a['date'] }}</td><td class="r num">{{ $a['amount'] }}@if ($a['tds'])<div class="muted">{{ __('incl. TDS :t', ['t' => $a['tds']]) }}</div>@endif</td></tr>@endforeach</tbody>
    </table>
  @endif
  @if ($d['unapplied'])<p class="small">{{ __('Kept as advance: :amount', ['amount' => $d['unapplied']]) }}</p>@endif
  @if ($d['cheque'])<p class="small muted">{{ __('Subject to realisation of the cheque.') }}</p>@endif
  @if ($f['signature'])<div class="r small" style="margin-top:14mm">{{ __('For :name', ['name' => $s['name']]) }}<br><span class="strong">{{ $L('signatory') }}</span></div>@endif
  @if ($design['footer'])<p class="small footer">{{ $design['footer'] }}</p>@endif
</div>
@endif
@include('documents._frame-close')
