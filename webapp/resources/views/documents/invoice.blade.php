@php
  $d = $doc;
  $s = $d['seller'];
  $b = $d['buyer'];
  $pageTitle = $d['title'] . ' ' . ($d['number'] ?? __('Draft'));
@endphp
@include('documents._frame-open')
@foreach ($d['copies'] as $copy)
<div class="sheet">
  @if ($d['is_draft'])<div class="watermark">{{ __('DRAFT') }}</div>@elseif ($d['is_void'])<div class="watermark">{{ __('VOID') }}</div>@endif

  {{-- Seller and document --}}
  <table class="box">
    <tr>
      <td style="width:58%; border-right:1px solid #374151">
        <div class="split" style="align-items:flex-start">
          @if ($s['logo'])<div style="flex:0 0 auto"><img class="logo" src="{{ $s['logo'] }}" alt=""></div>@endif
          <div>
            <div class="strong" style="font-size:13pt">{{ $s['name'] }}</div>
            @if ($s['legal_name'] && $s['legal_name'] !== $s['name'])<div class="small">{{ $s['legal_name'] }}</div>@endif
            @foreach ($s['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
            <div class="small">{{ collect([$s['phone'], $s['email'], $s['website']])->filter()->join(' · ') }}</div>
            @if ($s['tax_id'])<div class="small"><span class="strong">{{ $s['tax_label'] }}</span> <span class="num">{{ $s['tax_id'] }}</span>@if ($s['state']) · {{ __('State') }} {{ $s['state'] }}@endif @if ($s['pan']) · PAN {{ $s['pan'] }}@endif</div>@endif
          </div>
        </div>
      </td>
      <td>
        <h1>{{ $d['title'] }}</h1>
        @if ($copy)<div style="margin:4px 0"><span class="copy">{{ $copy }}</span></div>@endif
        <table class="small">
          <tr><td class="label" style="padding-left:0">{{ __('No.') }}</td><td class="num strong">{{ $d['number'] ?? __('Draft') }}</td></tr>
          <tr><td class="label" style="padding-left:0">{{ __('Date') }}</td><td class="num">{{ $d['issue_date'] }}</td></tr>
          @if ($d['due_date'])<tr><td class="label" style="padding-left:0">{{ __('Due') }}</td><td class="num">{{ $d['due_date'] }}</td></tr>@endif
          @if ($d['place_of_supply'])<tr><td class="label" style="padding-left:0">{{ __('Place of supply') }}</td><td>{{ $d['place_of_supply'] }}</td></tr>@endif
          @if ($d['reverse_charge'])<tr><td class="label" style="padding-left:0">{{ __('Reverse charge') }}</td><td class="strong">{{ $d['reverse_charge'] }}</td></tr>@endif
          @if ($d['reference'])<tr><td class="label" style="padding-left:0">{{ __('Your ref.') }}</td><td>{{ $d['reference'] }}</td></tr>@endif
          @if ($d['period'])<tr><td class="label" style="padding-left:0">{{ __('Period') }}</td><td class="num">{{ $d['period'] }}</td></tr>@endif
          @if ($d['against'])<tr><td class="label" style="padding-left:0">{{ __('Against') }}</td><td class="num">{{ $d['against']['number'] }} · {{ $d['against']['date'] }}</td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  {{-- Buyer --}}
  <table class="box" style="border-top:0">
    <tr>
      <td>
        <div class="label">{{ $d['type'] === 'credit_note' ? __('Credited to') : __('Bill to') }}</div>
        <div class="strong">{{ $b['name'] }}</div>
        @foreach ($b['address'] as $line)<div class="small">{{ $line }}</div>@endforeach
        @if ($b['tax_id'])<div class="small"><span class="strong">{{ $s['tax_label'] }}</span> <span class="num">{{ $b['tax_id'] }}</span>@if ($b['state']) · {{ __('State') }} {{ $b['state'] }}@endif</div>
        @elseif ($s['tax_label'] === 'GSTIN')<div class="small muted">{{ __('Unregistered') }}</div>@endif
      </td>
      @if ($d['reason'])<td style="width:40%; border-left:1px solid #374151"><div class="label">{{ __('Reason') }}</div><div class="small">{{ $d['reason'] }}</div></td>@endif
    </tr>
  </table>

  {{-- Lines --}}
  <table class="grid" style="margin-top:8px">
    <thead>
      <tr>
        <th class="c" style="width:5%">#</th><th>{{ __('Description') }}</th><th class="nowrap">{{ $s['tax_label'] === 'GSTIN' ? 'SAC' : __('Code') }}</th>
        <th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Rate') }}</th><th class="r">{{ __('Taxable') }}</th><th class="r">{{ $d['tax_label'] }}</th><th class="r">{{ __('Amount') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($d['lines'] as $i => $l)
        <tr>
          <td class="c">{{ $i + 1 }}</td>
          <td>{{ $l['description'] }}@if ($l['detail'])<div class="small muted">{{ $l['detail'] }}</div>@endif @if ($l['discount'])<div class="small muted">{{ __('Less :d discount', ['d' => $l['discount']]) }}</div>@endif</td>
          <td class="num small">{{ $l['sac'] }}</td>
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
      @if ($d['taxes'])
        <table class="grid small">
          <thead><tr><th>{{ __('Tax') }}</th><th class="r">{{ __('Taxable') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @foreach ($d['taxes'] as $t)
              <tr><td>{{ $t['label'] }}@unless ($t['charged'])<div class="muted">{{ __('payable by the recipient (reverse charge)') }}</div>@endunless</td><td class="r num">{{ $t['taxable'] }}</td><td class="r num">{{ $t['amount'] }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif
      @foreach ($d['legal_texts'] as $text)<p class="small strong" style="margin:6px 0 0">{{ $text }}</p>@endforeach
      @if ($d['tds'])<p class="small" style="margin:6px 0 0">{{ $d['tds'] }}</p>@endif
      @if ($d['pending_evidence'])<p class="small" style="margin:6px 0 0; color:#b91c1c">{{ __('Zero-rated subject to the export papers being on file.') }}</p>@endif
      @if ($d['tax_error'])<p class="small no-print" style="margin:6px 0 0; color:#b91c1c">{{ $d['tax_error'] }}</p>@endif
    </div>
    <table class="box" style="width:46%; flex:0 0 46%">
      <tr><td>{{ __('Taxable value') }}</td><td class="r num">{{ $d['taxable_total'] }}</td></tr>
      @if ($d['discount'])<tr><td>{{ __('Discount') }}</td><td class="r num">− {{ $d['discount'] }}</td></tr>@endif
      @foreach ($d['taxes'] as $t)@if ($t['charged'])<tr><td>{{ $t['label'] }}</td><td class="r num">{{ $t['amount'] }}</td></tr>@endif @endforeach
      @if ($d['round_off'])<tr><td>{{ __('Round off') }}</td><td class="r num">{{ $d['round_off'] }}</td></tr>@endif
      <tr style="border-top:1px solid #374151"><td class="strong">{{ __('Total') }}</td><td class="r num strong" style="font-size:12pt">{{ $d['total'] }}</td></tr>
      @if ($d['tax_notional'])<tr><td class="small muted" colspan="2">{{ __('GST of :amount is payable by the recipient under reverse charge.', ['amount' => $d['tax_notional']]) }}</td></tr>@endif
      @if ($d['advance_less'])<tr><td>{{ __('Less advance received') }}</td><td class="r num">− {{ $d['advance_less'] }}</td></tr>@endif
      @if ($d['paid'])<tr><td>{{ __('Received') }}</td><td class="r num">− {{ $d['paid'] }}</td></tr>@endif
      @if ($d['balance'] && ($d['advance_less'] || $d['paid']))<tr><td class="strong">{{ __('Balance due') }}</td><td class="r num strong">{{ $d['balance'] }}</td></tr>@endif
      <tr><td colspan="2" class="small"><span class="label">{{ __('In words') }}</span><br>{{ $d['total_words'] }}</td></tr>
    </table>
  </div>

  {{-- Bank, notes, sign-off --}}
  <table class="box" style="margin-top:8px">
    <tr>
      <td style="width:50%">
        @if ($d['bank'])
          <div class="label">{{ __('Pay to') }}</div>
          <div class="small">{{ $d['bank']['holder_name'] }} · {{ $d['bank']['bank_name'] }}@if ($d['bank']['branch']), {{ $d['bank']['branch'] }}@endif</div>
          @if ($d['bank']['account_number'])<div class="small">{{ __('A/c') }} <span class="num">{{ $d['bank']['account_number'] }}</span>@if ($d['bank']['ifsc']) · IFSC <span class="num">{{ $d['bank']['ifsc'] }}</span>@endif</div>@endif
          @if ($d['bank']['iban'])<div class="small">IBAN <span class="num">{{ $d['bank']['iban'] }}</span>@if ($d['bank']['swift']) · SWIFT {{ $d['bank']['swift'] }}@endif</div>@endif
          @if ($d['bank']['upi_id'])<div class="small">UPI {{ $d['bank']['upi_id'] }}</div>@endif
        @endif
        @if ($d['notes'])<div class="small" style="margin-top:6px; white-space:pre-line">{{ $d['notes'] }}</div>@endif
        @if ($d['terms'])<div class="small muted" style="margin-top:6px; white-space:pre-line">{{ $d['terms'] }}</div>@endif
      </td>
      <td style="width:18%" class="c qr">@if ($d['qr']){!! $d['qr']['svg'] !!}<div class="small">{{ __('Scan to pay') }}</div>@endif</td>
      <td class="r" style="vertical-align:bottom">
        <div class="small">{{ __('For :name', ['name' => $s['legal_name'] ?: $s['name']]) }}</div>
        <div style="height:16mm"></div>
        <div class="small strong">{{ __('Authorised signatory') }}</div>
      </td>
    </tr>
  </table>
  <p class="small muted c" style="margin-top:6px">{{ __('This is a computer-generated document.') }}</p>
</div>
@endforeach

{{-- The LR annexure of a consolidated bill (blueprint 10 §5). --}}
@if ($d['annexure'])
<div class="sheet">
  <h1 style="font-size:12pt">{{ __('Annexure to :doc :no', ['doc' => $d['title'], 'no' => $d['number'] ?? __('Draft')]) }}</h1>
  <p class="small">{{ __('Total trips / LRs: :n', ['n' => $d['lr_count']]) }}</p>
  <table class="grid small">
    <thead><tr><th>{{ __('LR no.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('From – to') }}</th><th>{{ __('Goods') }}</th><th class="r">{{ __('Weight') }}</th><th class="r">{{ __('Freight') }}</th><th class="r">{{ __('Other') }}</th><th class="r">{{ __('Total') }}</th></tr></thead>
    <tbody>
      @foreach ($d['annexure'] as $row)
        <tr><td class="num">{{ $row['lr'] }}</td><td class="num">{{ $row['date'] }}</td><td class="num">{{ $row['vehicle'] }}</td><td>{{ $row['from'] }} – {{ $row['to'] }}</td><td>{{ $row['goods'] }}</td><td class="r num">{{ $row['weight'] }}</td><td class="r num">{{ $row['freight'] }}</td><td class="r num">{{ $row['other'] }}</td><td class="r num strong">{{ $row['total'] }}</td></tr>
      @endforeach
    </tbody>
    <tfoot><tr><td colspan="8" class="r strong">{{ __('Subtotal (:n LRs)', ['n' => $d['lr_count']]) }}</td><td class="r num strong">{{ $d['taxable_total'] }}</td></tr></tfoot>
  </table>
</div>
@endif
@include('documents._frame-close')
