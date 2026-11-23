{{-- The LR on a pre-printed form (M51): no boxes, labels or logo — the form has them —, each value at its slot's
     x / y in mm from the paper's corner, moved by the printer offset; the freight left out on a copy that hides it. --}}
@php
  $party = fn (?array $p) => $p ? collect([$p['name'], ...$p['address'], $p['tax_id'] ? $s['tax_label'] . ' ' . $p['tax_id'] : null])->filter()->join("\n") : '';
@endphp
@foreach ($d['copies'] as $copy)
<div class="sheet">
  @if ($d['is_cancelled'])<div class="watermark">{{ __('CANCELLED') }}</div>@endif
  @php
    $values = [
      'lr_number' => $d['number'], 'date' => $d['date'], 'vehicle' => $d['vehicle'], 'from' => $d['from'], 'to' => $d['to'],
      'consignor' => $party($d['consignor']), 'consignee' => $party($d['consignee']), 'billing' => $d['billing']['name'] ?? '',
      'packages' => $d['packages'], 'goods' => $d['goods'], 'actual_weight' => $d['actual_weight'], 'charged_weight' => $d['charged_weight'],
      'total' => $copy['hide_freight'] ? '' : $money($d['total']),
      'charges' => $copy['hide_freight'] ? '' : collect($d['charges'])->map(fn ($amount, $label) => $label . '  ' . $money($amount))->join("\n"),
      'payment' => $d['payment'], 'invoice' => $d['invoice'] ?? '', 'ewaybill' => collect($d['ewaybills'])->join(', '),
      'declared_value' => $d['declared_value'] ?? '', 'driver' => $d['driver'] ?? '',
      'remarks' => trim(($d['client_ref'] ? __('Client ref.') . ' ' . $d['client_ref'] . '. ' : '') . ($d['remarks'] ?? '')),
    ];
  @endphp
  @foreach ($design['slots'] as $key => $slot)
    @continue(! $slot['on'] || ! isset($values[$key]) || (string) $values[$key] === '')
    <div class="slot{{ in_array($key, ['lr_number', 'total'], true) ? ' strong' : '' }}" data-slot="{{ $key }}" title="{{ __(\App\Modules\Invoicing\Design\DesignSchema::SLOTS['lr'][$key][0]) }}"
      style="left: {{ (int) $slot['x'] + (int) $design['offset_x'] }}mm; top: {{ (int) $slot['y'] + (int) $design['offset_y'] }}mm; width: {{ (int) $slot['w'] }}mm; white-space: pre-line">{{ $values[$key] }}</div>
  @endforeach
</div>
@endforeach
