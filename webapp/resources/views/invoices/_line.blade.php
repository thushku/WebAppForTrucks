{{-- One row of the bill builder. $i is the row index ("__N__" in the template), $line the saved line or null. --}}
@php($l = $line)
<tr class="align-top">
  <td>
    <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $l?->id }}">
    <select name="lines[{{ $i }}][service_class]" class="input w-44 py-1.5 text-sm" aria-label="{{ __('Charge') }}">
      @foreach ($classes as $code => $name)<option value="{{ $code }}" @selected(($l?->service_class ?? 'FREIGHT_ROAD') === $code)>{{ __($name) }}</option>@endforeach
    </select>
    @if ($l?->consignment)<div class="mt-1 text-xs text-ink-500">LR {{ $l->consignment->lr_number }}</div>@endif
  </td>
  <td>
    <input name="lines[{{ $i }}][description]" value="{{ $l?->description }}" class="input min-w-72 py-1.5 text-sm" maxlength="255" aria-label="{{ __('Description') }}">
    <input name="lines[{{ $i }}][detail]" value="{{ $l?->detail }}" class="input mt-1 min-w-72 py-1 text-xs" maxlength="255" placeholder="{{ __('Detail (optional)') }}" aria-label="{{ __('Detail') }}">
  </td>
  <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.001" min="0" value="{{ $l ? rtrim(rtrim((string) $l->quantity, '0'), '.') : 1 }}" class="input w-20 py-1.5 text-end text-sm" aria-label="{{ __('Quantity') }}">
    <input name="lines[{{ $i }}][unit]" value="{{ $l?->unit ?? 'trip' }}" class="input mt-1 w-20 py-1 text-xs" maxlength="12" aria-label="{{ __('Unit') }}"></td>
  <td><input name="lines[{{ $i }}][unit_rate]" type="number" step="0.01" min="0" value="{{ $l ? (float) $l->unit_rate : '' }}" class="input w-32 py-1.5 text-end text-sm" aria-label="{{ __('Rate') }}">
    <input name="lines[{{ $i }}][discount_percent]" type="number" step="0.01" min="0" max="100" value="{{ $l && (float) $l->discount_percent ? (float) $l->discount_percent : '' }}" class="input mt-1 w-32 py-1 text-end text-xs" placeholder="{{ __('Discount %') }}" aria-label="{{ __('Discount %') }}"></td>
  <td>
    <select name="lines[{{ $i }}][tax_code]" class="input w-48 py-1.5 text-sm" aria-label="{{ __('Tax') }}">
      <option value="">{{ __('By the rules') }}{{ $l?->tax_code && ! $l?->override_reason ? ' (' . $l->tax_code . ')' : '' }}</option>
      @foreach ($codes as $code)<option value="{{ $code->code }}" @selected($l?->override_reason && $l?->tax_code === $code->code)>{{ $code->name }}</option>@endforeach
    </select>
    <input name="lines[{{ $i }}][override_reason]" value="{{ $l?->override_reason }}" class="input mt-1 w-48 py-1 text-xs" maxlength="255" placeholder="{{ __('Why changed by hand') }}" aria-label="{{ __('Why changed by hand') }}">
  </td>
  <td class="money whitespace-nowrap text-end text-sm">
    @if ($l)
      {{ \App\Support\Money::company($l->line_total) }}
      <div class="text-xs text-ink-500">{{ $l->sac_code ? 'SAC ' . $l->sac_code . ' · ' : '' }}{{ (float) $l->rate_percent ? \App\Modules\Tax\Services\TaxCalculator::pct((float) $l->rate_percent) . '%' : ($l->tax_category ?? '') }}{{ (float) $l->notional_tax > 0 ? ' RCM' : '' }}</div>
    @endif
  </td>
  <td><button type="button" class="btn-ghost px-2 py-1 text-xs text-overdue" data-remove-row aria-label="{{ __('Remove the line') }}"><x-icon name="trash" class="size-4" /></button></td>
</tr>
