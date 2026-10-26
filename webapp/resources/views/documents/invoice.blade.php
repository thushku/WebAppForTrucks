@use('App\Modules\Invoicing\Design\DesignSchema')
@php
  $d = $doc;
  $s = $d['seller'];
  $b = $d['buyer'];
  $design = $d['design'];
  $f = $design['fields'];
  $L = fn (string $key) => DesignSchema::label($design, $key);
  $pageTitle = $d['title'] . ' ' . ($d['number'] ?? __('Draft'));
@endphp
@include('documents._frame-open')
@foreach ($d['copies'] as $copy)
<div class="sheet">
  @if ($d['is_draft'])<div class="watermark">{{ __('DRAFT') }}</div>@elseif ($d['is_void'])<div class="watermark">{{ __('VOID') }}</div>@endif

  {{-- Seller and document --}}
  <table class="box head">
    <tr>
      <td class="sep-r" style="width:58%">
        <div class="split" style="align-items:flex-start">
          @if ($f['logo'] && $s['logo'])<div style="flex:0 0 auto"><img class="logo" src="{{ $s['logo'] }}" alt=""></div>@endif
          <div>
            <div class="strong big">{{ $s['name'] }}</div>
            @if ($s['legal_name'] && $s['legal_name'] !== $s['name'])<div class="small">{{ $s['legal_name'] }}</div>@endif
            @if ($f['address'])@foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach @endif
            @if ($f['contact'])<div class="small">{{ collect([$s['phone'], $s['email'], $s['website']])->filter()->join(' · ') }}</div>@endif
            @if ($f['tax_registration'] && $s['tax_id'])<div class="small"><span class="strong">{{ $s['tax_label'] }}</span> <span class="num">{{ $s['tax_id'] }}</span>@if ($s['state']) · {{ __('State') }} {{ $s['state'] }}@endif @if ($f['pan'] && $s['pan']) · PAN {{ $s['pan'] }}@endif</div>
            @elseif ($f['pan'] && $s['pan'])<div class="small">PAN {{ $s['pan'] }}</div>@endif
            @foreach ($design['custom'] as $c)<div class="small">@if ($c['label'])<span class="strong">{{ $c['label'] }}</span> @endif{{ $c['value'] }}</div>@endforeach
          </div>
        </div>
      </td>
      <td>
        <h1>{{ $d['title'] }}</h1>
        @if ($copy)<div style="margin:4px 0"><span class="copy">{{ $copy }}</span></div>@endif
        <table class="small">
          <tr><td class="label" style="padding-inline-start:0">{{ __('No.') }}</td><td class="num strong">{{ $d['number'] ?? __('Draft') }}</td></tr>
          <tr><td class="label" style="padding-inline-start:0">{{ __('Date') }}</td><td class="num">{{ $d['issue_date'] }}</td></tr>
          @if ($d['due_date'])<tr><td class="label" style="padding-inline-start:0">{{ __('Due') }}</td><td class="num">{{ $d['due_date'] }}</td></tr>@endif
          @if ($d['place_of_supply'])<tr><td class="label" style="padding-inline-start:0">{{ $L('place_of_supply') }}</td><td>{{ $d['place_of_supply'] }}</td></tr>@endif
          @if ($d['reverse_charge'])<tr><td class="label" style="padding-inline-start:0">{{ $L('reverse_charge') }}</td><td class="strong">{{ $d['reverse_charge'] }}</td></tr>@endif
          @if ($d['reference'])<tr><td class="label" style="padding-inline-start:0">{{ __('Your ref.') }}</td><td>{{ $d['reference'] }}</td></tr>@endif
          @if ($d['period'])<tr><td class="label" style="padding-inline-start:0">{{ __('Period') }}</td><td class="num">{{ $d['period'] }}</td></tr>@endif
          @if ($d['against'])<tr><td class="label" style="padding-inline-start:0">{{ __('Against') }}</td><td class="num">{{ $d['against']['number'] }} · {{ $d['against']['date'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  {{-- Buyer --}}
  <table class="box" style="border-top:0">
    <tr>
      <td>
        <div class="label">{{ $d['type'] === 'credit_note' ? __('Credited to') : $L('bill_to') }}</div>
        <div class="strong">{{ $b['name'] }}</div>
        @foreach ($b['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
        @if ($b['tax_id'])<div class="small"><span class="strong">{{ $s['tax_label'] }}</span> <span class="num">{{ $b['tax_id'] }}</span>@if ($b['state']) · {{ __('State') }} {{ $b['state'] }}@endif</div>
        @elseif ($s['tax_label'] === 'GSTIN')<div class="small muted">{{ __('Unregistered') }}</div>@endif
      </td>
      @if ($d['reason'])<td class="sep-l" style="width:40%"><div class="label">{{ __('Reason') }}</div><div class="small">{{ $d['reason'] }}</div></td>@endif
    </tr>
  </table>

  {{-- Lines --}}
  <table class="grid" style="margin-top:8px">
    <thead>
      <tr>
        <th class="c" style="width:5%">#</th><th>{{ $L('description') }}</th>@if ($f['sac'])<th class="nowrap">{{ $s['tax_label'] === 'GSTIN' ? 'SAC' : __('Code') }}</th>@endif
        <th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Taxable') }}</th><th class="r">{{ $d['tax_label'] }}</th><th class="r">{{ __('Amount') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($d['lines'] as $i => $l)
        <tr>
          <td class="c">{{ $i + 1 }}</td>
          <td>{{ $l['description'] }}@if ($l['detail'])<div class="small muted">{{ $l['detail'] }}</div>@endif @if ($l['discount'])<div class="small muted">{{ __('Less :d discount', ['d' => $l['discount']]) }}</div>@endif</td>
          @if ($f['sac'])<td class="num small">{{ $l['sac'] }}</td>@endif
          <td class="r num">{{ $l['qty'] }} <span class="small muted">{{ $l['unit'] }}</span></td>
          <td class="r num">{{ $l['rate'] }}</td>
          <td class="r num">{{ $l['taxable'] }}</td>
          <td class="r num">{{ $l['tax_rate'] }}@if ($l['notional'])<div class="small muted">{{ __('RCM') }}</div>@endif</td>
          <td class="r num strong">{{ $l['amount'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  {{-- Tax and totals --}}
  <div class="split" style="margin-top:8px; align-items:flex-start">
    <div>
      @if ($f['tax_summary'] && $d['taxes'])
        <table class="grid small">
          <thead><tr><th>{{ __('Tax') }}</th><th class="r">{{ __('Taxable') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @foreach ($d['taxes'] as $t)
              <tr><td>{{ $t['label'] }}@unless ($t['charged'])<div class="muted">{{ __('payable by the recipient (reverse charge)') }}</div>@endunless</td><td class="r num">{{ $t['taxable'] }}</td><td class="r num">{{ $t['amount'] }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif
      {{-- The legal lines (reverse charge, exemptions) always print: they are what makes the bill lawful. --}}
      @foreach ($d['legal_texts'] as $text)<p class="small strong" style="margin:6px 0 0">{{ $text }}</p>@endforeach
      @if ($f['tds_note'] && $d['tds'])<p class="small" style="margin:6px 0 0">{{ $d['tds'] }}</p>@endif
      @if ($d['pending_evidence'])<p class="small" style="margin:6px 0 0; color:#b91c1c">{{ __('Zero-rated subject to the export papers being on file.') }}</p>@endif
      @if ($d['tax_error'])<p class="small no-print" style="margin:6px 0 0; color:#b91c1c">{{ $d['tax_error'] }}</p>@endif
    </div>
    <table class="box" style="width:46%; flex:0 0 46%">
      <tr><td>{{ __('Taxable value') }}</td><td class="r num">{{ $d['taxable_total'] }}</td></tr>
      @if ($d['discount'])<tr><td>{{ __('Discount') }}</td><td class="r num">− {{ $d['discount'] }}</td></tr>@endif
      @foreach ($d['taxes'] as $t)@if ($t['charged'])<tr><td>{{ $t['label'] }}</td><td class="r num">{{ $t['amount'] }}</td></tr>@endif @endforeach
      @if ($d['round_off'])<tr><td>{{ __('Round off') }}</td><td class="r num">{{ $d['round_off'] }}</td></tr>@endif
      <tr class="total-row sep-t"><td class="strong">{{ $L('total') }}</td><td class="r num strong mid">{{ $d['total'] }}</td></tr>
      @if ($d['tax_notional'])<tr><td class="small muted" colspan="2">{{ __('GST of :amount is payable by the recipient under reverse charge.', ['amount' => $d['tax_notional']]) }}</td></tr>@endif
      @if ($d['advance_less'])<tr><td>{{ __('Less advance received') }}</td><td class="r num">− {{ $d['advance_less'] }}</td></tr>@endif
      @if ($d['paid'])<tr><td>{{ __('Received') }}</td><td class="r num">− {{ $d['paid'] }}</td></tr>@endif
      @if ($d['balance'] && ($d['advance_less'] || $d['paid']))<tr><td class="strong">{{ $L('balance') }}</td><td class="r num strong">{{ $d['balance'] }}</td></tr>@endif
      @if ($f['words'])<tr><td colspan="2" class="small"><span class="label">{{ $L('in_words') }}</span><br>{{ $d['total_words'] }}</td></tr>@endif
    </table>
  </div>

  {{-- Bank, notes, sign-off --}}
  @php $showBank = $f['bank'] && $d['bank']; $showQr = $f['qr'] && $d['qr']; @endphp
  @if ($showBank || $showQr || ($f['notes'] && $d['notes']) || ($f['terms'] && $d['terms']) || $f['signature'])
  <table class="box" style="margin-top:8px">
    <tr>
      <td style="width:50%">
        @if ($showBank)
          <div class="label">{{ $L('pay_to') }}</div>
          <div class="small">{{ $d['bank']['holder_name'] }} · {{ $d['bank']['bank_name'] }}@if ($d['bank']['branch']), {{ $d['bank']['branch'] }}@endif</div>
          @if ($d['bank']['account_number'])<div class="small">{{ __('A/c') }} <span class="num">{{ $d['bank']['account_number'] }}</span>@if ($d['bank']['ifsc']) · IFSC <span class="num">{{ $d['bank']['ifsc'] }}</span>@endif</div>@endif
          @if ($d['bank']['iban'])<div class="small">IBAN <span class="num">{{ $d['bank']['iban'] }}</span>@if ($d['bank']['swift']) · SWIFT {{ $d['bank']['swift'] }}@endif</div>@endif
          @if ($d['bank']['upi_id'])<div class="small">UPI {{ $d['bank']['upi_id'] }}</div>@endif
        @endif
        @if ($f['notes'] && $d['notes'])<div class="small" style="margin-top:6px; white-space:pre-line">{{ $d['notes'] }}</div>@endif
        @if ($f['terms'] && $d['terms'])<div class="small muted" style="margin-top:6px; white-space:pre-line">{{ $d['terms'] }}</div>@endif
      </td>
      <td style="width:18%" class="c qr">@if ($showQr){!! $d['qr']['svg'] !!}<div class="small">{{ __('Scan to pay') }}</div>@endif</td>
      <td class="r" style="vertical-align:bottom">
        @if ($f['signature'])
          <div class="small">{{ __('For :name', ['name' => $s['legal_name'] ?: $s['name']]) }}</div>
          <div style="height:16mm"></div>
          <div class="small strong">{{ $L('signatory') }}</div>
        @endif
      </td>
    </tr>
  </table>
  @endif
  @if ($f['computer_generated'])<p class="small muted c" style="margin-top:6px">{{ __('This is a computer-generated document.') }}</p>@endif
  @if ($design['footer'])<p class="small footer">{{ $design['footer'] }}</p>@endif
</div>
@endforeach

{{-- The LR annexure of a consolidated bill (blueprint 10 §5). --}}
@if ($f['annexure'] && $d['annexure'])
<div class="sheet">
  <h1 class="mid">{{ __('Annexure to :doc :no', ['doc' => $d['title'], 'no' => $d['number'] ?? __('Draft')]) }}</h1>
  <p class="small">{{ __('Total trips / LRs: :n', ['n' => $d['lr_count']]) }}</p>
  <table class="grid small">
    <thead><tr><th>{{ __('LR no.') }}</th><th>{{ __('Date') }}</th>@if ($f['annexure_vehicle'])<th>{{ __('Vehicle') }}</th>@endif @if ($f['annexure_driver'] ?? false)<th>{{ __('Driver') }}</th>@endif<th>{{ __('From – to') }}</th><th>{{ __('Goods') }}</th><th class="r">{{ __('Weight') }}</th><th class="r">{{ __('Freight') }}</th><th class="r">{{ __('Other') }}</th><th class="r">{{ $L('total') }}</th></tr></thead>
    <tbody>
      @foreach ($d['annexure'] as $row)
        <tr><td class="num">{{ $row['lr'] }}</td><td class="num">{{ $row['date'] }}</td>@if ($f['annexure_vehicle'])<td class="num">{{ $row['vehicle'] }}</td>@endif @if ($f['annexure_driver'] ?? false)<td>{{ $row['driver'] ?? '' }}</td>@endif<td>{{ $row['from'] }} – {{ $row['to'] }}</td><td>{{ $row['goods'] }}</td><td class="r num">{{ $row['weight'] }}</td><td class="r num">{{ $row['freight'] }}</td><td class="r num">{{ $row['other'] }}</td><td class="r num strong">{{ $row['total'] }}</td></tr>
      @endforeach
    </tbody>
    <tfoot><tr class="total-row"><td colspan="{{ 7 + ($f['annexure_vehicle'] ? 1 : 0) + (($f['annexure_driver'] ?? false) ? 1 : 0) }}" class="r strong">{{ __('Subtotal (:n LRs)', ['n' => $d['lr_count']]) }}</td><td class="r num strong">{{ $d['taxable_total'] }}</td></tr></tfoot>
  </table>
</div>
@endif
@include('documents._frame-close')
