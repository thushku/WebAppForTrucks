@php
  $d = $doc;
  $s = $d['seller'];
  $money = $d['money'];
  $pageTitle = __('LR') . ' ' . $d['number'];
@endphp
@include('documents._frame-open')
@foreach ($d['copies'] as $copy)
<div class="sheet" style="background: {{ $copy['tint'] ?? '#fff' }}">
  @if ($d['is_cancelled'])<div class="watermark">{{ __('CANCELLED') }}</div>@endif
  <table class="box">
    <tr>
      <td style="width:62%; border-right:1px solid #374151">
        <div class="split" style="align-items:flex-start">
          @if ($s['logo'])<div style="flex:0 0 auto"><img class="logo" src="{{ $s['logo'] }}" alt=""></div>@endif
          <div>
            <div class="strong" style="font-size:14pt">{{ $s['name'] }}</div>
            @foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
            <div class="small">{{ collect([$s['phone'], $s['email']])->filter()->join(' · ') }}</div>
            @if ($s['tax_id'])<div class="small"><span class="strong">{{ $s['tax_label'] }}</span> <span class="num">{{ $s['tax_id'] }}</span>@if ($s['pan']) · PAN {{ $s['pan'] }}@endif</div>@endif
          </div>
        </div>
      </td>
      <td class="c">
        <h1 style="font-size:12pt">{{ __('Consignment note') }}</h1>
        <div class="small">{{ __('Lorry receipt') }}</div>
        <div style="margin:4px 0"><span class="copy">{{ $copy['label'] }}</span></div>
        <div class="strong" style="font-size:10pt">{{ $d['risk'] }}</div>
      </td>
    </tr>
  </table>

  <table class="grid" style="border-top:0">
    <tr>
      <td><span class="label">{{ __('LR no.') }}</span><div class="num strong" style="font-size:12pt">{{ $d['number'] }}</div></td>
      <td><span class="label">{{ __('Date') }}</span><div class="num">{{ $d['date'] }}</div></td>
      <td><span class="label">{{ __('Vehicle') }}</span><div class="num strong">{{ $d['vehicle'] }}</div></td>
      <td><span class="label">{{ __('From') }}</span><div>{{ $d['from'] }}</div></td>
      <td><span class="label">{{ __('To') }}</span><div>{{ $d['to'] }}</div></td>
    </tr>
    <tr>
      <td colspan="2"><span class="label">{{ __('Trip') }}</span><div class="num small">{{ $d['trip'] }}</div></td>
      <td colspan="3"><span class="label">{{ __('Driver') }}</span><div class="small">{{ $d['driver'] }}</div></td>
    </tr>
  </table>

  <table class="grid" style="border-top:0">
    <tr>
      @foreach ([__('Consignor') => $d['consignor'], __('Consignee') => $d['consignee']] as $label => $p)
        <td style="width:50%">
          <span class="label">{{ $label }}</span>
          @if ($p)
            <div class="strong">{{ $p['name'] }}</div>
            @foreach ($p['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
            @if ($p['tax_id'])<div class="small">{{ $s['tax_label'] }} <span class="num">{{ $p['tax_id'] }}</span></div>@endif
            @if ($p['phone'])<div class="small num">{{ $p['phone'] }}</div>@endif
          @endif
        </td>
      @endforeach
    </tr>
    @if ($d['billing'])<tr><td colspan="2"><span class="label">{{ __('Freight billed to') }}</span> <span class="strong">{{ $d['billing']['name'] }}</span>@if ($d['billing']['tax_id']) · <span class="num small">{{ $d['billing']['tax_id'] }}</span>@endif</td></tr>@endif
  </table>

  <table class="grid" style="border-top:0">
    <thead><tr><th>{{ __('Packages') }}</th><th>{{ __('Said to contain') }}</th><th class="r">{{ __('Actual weight') }}</th><th class="r">{{ __('Charged weight') }}</th>
      @unless ($copy['hide_freight'])<th class="r">{{ __('Charges') }}</th>@endunless</tr></thead>
    <tbody>
      <tr>
        <td class="num">{{ $d['packages'] }}</td>
        <td>{{ $d['goods'] }}@if ($d['hazardous'])<div class="strong" style="color:#b91c1c">{{ __('Hazardous') }}</div>@endif
          @if ($d['seal'])<div class="small">{{ __('Seal') }} {{ $d['seal'] }}</div>@endif @if ($d['container'])<div class="small">{{ __('Container') }} {{ $d['container'] }}</div>@endif</td>
        <td class="r num">{{ $d['actual_weight'] }}</td>
        <td class="r num">{{ $d['charged_weight'] }}</td>
        @unless ($copy['hide_freight'])
          <td class="r" style="width:34%">
            <table class="small">
              @foreach ($d['charges'] as $label => $amount)<tr><td style="padding:1px 0">{{ $label }}</td><td class="r num" style="padding:1px 0">{{ $money($amount) }}</td></tr>@endforeach
              <tr><td class="strong" style="padding:2px 0; border-top:1px solid #9ca3af">{{ __('Total') }}</td><td class="r num strong" style="padding:2px 0; border-top:1px solid #9ca3af">{{ $money($d['total']) }}</td></tr>
              @if ($d['advance'] > 0)<tr><td style="padding:1px 0">{{ __('Advance') }}</td><td class="r num" style="padding:1px 0">− {{ $money($d['advance']) }}</td></tr>@endif
            </table>
          </td>
        @endunless
      </tr>
    </tbody>
  </table>

  <table class="grid small" style="border-top:0">
    <tr>
      <td><span class="label">{{ __('Freight') }}</span><div class="strong">{{ $d['payment'] }}</div></td>
      @if ($d['gst_by'])<td><span class="label">{{ __('GST payable by') }}</span><div class="strong">{{ $d['gst_by'] }}</div></td>@endif
      <td><span class="label">{{ __('Consignor invoice') }}</span><div class="num">{{ $d['invoice'] ?? '—' }}</div></td>
      <td><span class="label">{{ __('E-way bill') }}</span><div class="num">{!! collect($d['ewaybills'])->map(fn ($e) => e($e))->join('<br>') ?: '—' !!}</div></td>
      @if ($d['declared_value'])<td><span class="label">{{ __('Declared value') }}</span><div class="num">{{ $d['declared_value'] }}</div></td>@endif
    </tr>
    @if ($d['client_ref'] || $d['remarks'])<tr><td colspan="5">@if ($d['client_ref']){{ __('Client ref.') }} {{ $d['client_ref'] }}. @endif{{ $d['remarks'] }}</td></tr>@endif
  </table>

  {{-- Signatures and the proof of delivery (blueprint 10 §4.1). --}}
  <table class="grid small" style="border-top:0">
    <tr>
      <td style="width:33%; height:26mm; vertical-align:bottom"><div class="label">{{ __('Consignor’s signature') }}</div></td>
      <td style="width:33%; vertical-align:bottom"><div class="label">{{ __('For :name', ['name' => $s['name']]) }}</div><div>{{ __('Booking clerk') }}</div></td>
      <td>
        <div class="label">{{ __('Proof of delivery') }}</div>
        <div>{{ __('Received the above in good condition / with remarks:') }}</div>
        <div style="margin-top:6px">{{ __('Packages') }} ______ &nbsp; {{ __('Short / damaged') }} ______</div>
        <div style="margin-top:6px">{{ __('Name, signature, stamp, date & time') }}</div>
      </td>
    </tr>
  </table>
  <p class="small muted" style="margin-top:6px">{{ __('Goods are carried subject to the conditions of the Carriage by Road Act, 2007. Not responsible for leakage, breakage or damage not caused by us.') }}</p>
</div>
@endforeach
@include('documents._frame-close')
