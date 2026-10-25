{{--
  The fields of one load. $n is the input name prefix ("load" on the new-trip
  form, "" on the consignment form); $c the consignment (or null).
--}}
@php
  $name = fn ($f) => $n ? "{$n}[{$f}]" : $f;
  $id   = fn ($f) => ($n ? "{$n}_" : 'c_') . $f;
  $old  = fn ($f, $default = null) => old($n ? "{$n}.{$f}" : $f, $c?->$f ?? $default);
  $err  = fn ($f) => $n ? "{$n}.{$f}" : $f;
  $dateVal = fn ($f) => ($v = $old($f)) instanceof \Carbon\CarbonInterface ? $v->toDateString() : $v;
  $india = app(\App\Platform\Tenancy\CompanyContext::class)->company()->country === 'IN';
@endphp
<div class="grid gap-4 sm:grid-cols-3" data-load data-rate-url="{{ route('trips.rate') }}">
  <div>
    <label class="label" for="{{ $id('billing_party_id') }}">{{ __('Billing party (pays)') }}</label>
    <select id="{{ $id('billing_party_id') }}" name="{{ $name('billing_party_id') }}" class="input" data-rate-party>
      <option value="">—</option>
      @foreach ($clients as $p)<option value="{{ $p->id }}" @selected((int) $old('billing_party_id') === $p->id)>{{ $p->name }}{{ $p->short_code ? ' (' . $p->short_code . ')' : '' }}</option>@endforeach
    </select>
    @error($err('billing_party_id'))<span class="error">{{ $message }}</span>@enderror
  </div>
  <div>
    <label class="label" for="{{ $id('consignor_party_id') }}">{{ __('Consignor (sends)') }}</label>
    <select id="{{ $id('consignor_party_id') }}" name="{{ $name('consignor_party_id') }}" class="input">
      <option value="">{{ __('Same as billing party') }}</option>
      @foreach ($partiesAll as $p)<option value="{{ $p->id }}" @selected((int) $old('consignor_party_id') === $p->id)>{{ $p->name }}</option>@endforeach
    </select>
  </div>
  <div>
    <label class="label" for="{{ $id('consignee_party_id') }}">{{ __('Consignee (receives)') }}</label>
    <select id="{{ $id('consignee_party_id') }}" name="{{ $name('consignee_party_id') }}" class="input">
      <option value="">—</option>
      @foreach ($partiesAll as $p)<option value="{{ $p->id }}" @selected((int) $old('consignee_party_id') === $p->id)>{{ $p->name }}</option>@endforeach
    </select>
  </div>
  <div>
    <label class="label" for="{{ $id('material') }}">{{ __('Material') }}</label>
    <select id="{{ $id('material') }}" name="{{ $name('material') }}" class="input" data-rate-material>
      <option value="">—</option>
      @foreach ($materials as $code => $label)<option value="{{ $code }}" @selected($old('material') === $code)>{{ $label }}</option>@endforeach
    </select>
  </div>
  <div class="sm:col-span-2"><label class="label" for="{{ $id('description') }}">{{ __('Said to contain') }}</label><input id="{{ $id('description') }}" name="{{ $name('description') }}" class="input" value="{{ $old('description') }}" maxlength="200" placeholder="{{ __('OPC 53 grade cement, 50 kg bags') }}"></div>
  <div class="grid grid-cols-2 gap-2">
    <div><label class="label" for="{{ $id('packages') }}">{{ __('Packages') }}</label><input id="{{ $id('packages') }}" name="{{ $name('packages') }}" type="number" min="0" class="input" value="{{ $old('packages') }}" data-calc-packages>@error($err('packages'))<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="{{ $id('package_type') }}">{{ __('Kind') }}</label><select id="{{ $id('package_type') }}" name="{{ $name('package_type') }}" class="input"><option value="">—</option>@foreach (\App\Modules\Trips\Models\Consignment::PACKAGE_TYPES as $pt)<option value="{{ $pt }}" @selected($old('package_type') === $pt)>{{ __(ucfirst($pt)) }}</option>@endforeach</select></div>
  </div>
  <div class="grid grid-cols-2 gap-2">
    <div><label class="label" for="{{ $id('actual_weight_mt') }}">{{ __('Weight (MT)') }}</label><input id="{{ $id('actual_weight_mt') }}" name="{{ $name('actual_weight_mt') }}" type="number" min="0" step="0.001" class="input" value="{{ $old('actual_weight_mt') }}" data-calc-weight>@error($err('actual_weight_mt'))<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="{{ $id('charged_weight_mt') }}">{{ __('Charged (MT)') }}</label><input id="{{ $id('charged_weight_mt') }}" name="{{ $name('charged_weight_mt') }}" type="number" min="0" step="0.001" class="input" value="{{ $old('charged_weight_mt') }}" data-calc-charged></div>
  </div>
  <div class="grid grid-cols-2 gap-2">
    <div><label class="label" for="{{ $id('freight_basis') }}">{{ __('Rate') }}</label><select id="{{ $id('freight_basis') }}" name="{{ $name('freight_basis') }}" class="input" data-calc-basis>@foreach (\App\Modules\Trips\Models\Consignment::BASES as $v => $label)<option value="{{ $v }}" @selected($old('freight_basis', 'trip') === $v)>{{ __($label) }}</option>@endforeach</select></div>
    <div><label class="label" for="{{ $id('rate') }}">&nbsp;</label><input id="{{ $id('rate') }}" name="{{ $name('rate') }}" type="number" min="0" step="0.01" class="input" value="{{ $old('rate') }}" data-calc-rate required aria-label="{{ __('Rate') }}">@error($err('rate'))<span class="error">{{ $message }}</span>@enderror</div>
  </div>
  <input type="hidden" name="{{ $name('rate_contract_line_id') }}" value="{{ $old('rate_contract_line_id') }}" data-rate-line>
  <div class="sm:col-span-3 -mt-2 text-xs text-ink-500" data-rate-note aria-live="polite"></div>
  <div><label class="label" for="{{ $id('loading_charge') }}">{{ __('Loading charge') }}</label><input id="{{ $id('loading_charge') }}" name="{{ $name('loading_charge') }}" type="number" min="0" step="0.01" class="input" value="{{ $old('loading_charge') ?: '' }}"></div>
  <div><label class="label" for="{{ $id('unloading_charge') }}">{{ __('Unloading charge') }}</label><input id="{{ $id('unloading_charge') }}" name="{{ $name('unloading_charge') }}" type="number" min="0" step="0.01" class="input" value="{{ $old('unloading_charge') ?: '' }}"></div>
  <div><label class="label" for="{{ $id('advance_from_party') }}">{{ __('Advance from the party') }}</label><input id="{{ $id('advance_from_party') }}" name="{{ $name('advance_from_party') }}" type="number" min="0" step="0.01" class="input" value="{{ $old('advance_from_party') ?: '' }}"></div>
  <div>
    <label class="label" for="{{ $id('payment_type') }}">{{ __('LR payment') }}</label>
    <select id="{{ $id('payment_type') }}" name="{{ $name('payment_type') }}" class="input">@foreach (\App\Modules\Trips\Models\Consignment::PAYMENT_TYPES as $v => $label)<option value="{{ $v }}" @selected($old('payment_type', 'to_be_billed') === $v)>{{ __($label) }}</option>@endforeach</select>
  </div>
  @if ($india)
    <div>
      <label class="label" for="{{ $id('gst_payable_by') }}">{{ __('GST paid by') }}</label>
      <select id="{{ $id('gst_payable_by') }}" name="{{ $name('gst_payable_by') }}" class="input"><option value="">—</option>@foreach (\App\Modules\Trips\Models\Consignment::GST_PAYERS as $v => $label)<option value="{{ $v }}" @selected($old('gst_payable_by', $gstDefault ?? null) === $v)>{{ __($label) }}</option>@endforeach</select>
    </div>
    <div class="grid grid-cols-[1fr_9rem] gap-2">
      <div><label class="label" for="{{ $id('eway_no') }}">{{ __('E-way bill no.') }}</label><input id="{{ $id('eway_no') }}" name="{{ $name('eway_no') }}" class="input font-mono" value="{{ old($n ? "{$n}.eway_no" : 'eway_no', $c?->ewaybills?->first()?->number) }}" maxlength="12" inputmode="numeric">@error($err('eway_no'))<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="{{ $id('eway_valid_until') }}">{{ __('Valid until') }}</label><input id="{{ $id('eway_valid_until') }}" name="{{ $name('eway_valid_until') }}" type="date" class="input" value="{{ old($n ? "{$n}.eway_valid_until" : 'eway_valid_until', $c?->ewaybills?->first()?->valid_until?->toDateString()) }}"></div>
    </div>
  @else
    <div>
      <label class="label" for="{{ $id('uae_transport_type') }}">{{ __('Transport type (VAT)') }}</label>
      <select id="{{ $id('uae_transport_type') }}" name="{{ $name('uae_transport_type') }}" class="input"><option value="">—</option>@foreach (['domestic' => __('Inside the UAE (5%)'), 'export' => __('Export (0%)'), 'import' => __('Import (0%)'), 'international' => __('UAE leg of international (0%)')] as $v => $label)<option value="{{ $v }}" @selected($old('uae_transport_type') === $v)>{{ $label }}</option>@endforeach</select>
    </div>
    <div>
      <label class="label" for="{{ $id('uae_role') }}">{{ __('We are') }}</label>
      <select id="{{ $id('uae_role') }}" name="{{ $name('uae_role') }}" class="input"><option value="">—</option><option value="main" @selected($old('uae_role') === 'main')>{{ __('Main carrier') }}</option><option value="sub" @selected($old('uae_role') === 'sub')>{{ __('Subcontractor') }}</option></select>
    </div>
  @endif
  <div><label class="label" for="{{ $id('consignor_invoice_no') }}">{{ __('Consignor invoice no.') }}</label><input id="{{ $id('consignor_invoice_no') }}" name="{{ $name('consignor_invoice_no') }}" class="input" value="{{ $old('consignor_invoice_no') }}" maxlength="40"></div>
  <div><label class="label" for="{{ $id('consignor_invoice_value') }}">{{ __('Invoice value') }}</label><input id="{{ $id('consignor_invoice_value') }}" name="{{ $name('consignor_invoice_value') }}" type="number" min="0" step="0.01" class="input" value="{{ $old('consignor_invoice_value') }}"></div>
  <div><label class="label" for="{{ $id('client_ref') }}">{{ __('Client PO / indent / shipment no.') }}</label><input id="{{ $id('client_ref') }}" name="{{ $name('client_ref') }}" class="input" value="{{ $old('client_ref') }}" maxlength="60"></div>
  <div class="flex flex-wrap items-center gap-5 sm:col-span-3 text-sm">
    <label class="flex items-center gap-2"><input type="checkbox" name="{{ $name('hazardous') }}" value="1" class="size-4 rounded border-ink-300" @checked($old('hazardous'))> {{ __('Hazardous goods') }}</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="{{ $name('over_dimension') }}" value="1" class="size-4 rounded border-ink-300" @checked($old('over_dimension'))> {{ __('Over-dimension') }}</label>
    <label class="flex items-center gap-2">{{ __('Risk') }} <select name="{{ $name('risk') }}" class="input w-36 py-1.5"><option value="owner" @selected($old('risk', 'owner') === 'owner')>{{ __('Owner’s risk') }}</option><option value="carrier" @selected($old('risk') === 'carrier')>{{ __('Carrier’s risk') }}</option></select></label>
  </div>
  <div class="sm:col-span-3 rounded-xl bg-ink-50 px-4 py-3 text-sm">{{ __('Freight') }}: <output class="money font-semibold" data-calc-freight>—</output> <span class="text-xs text-ink-500">({{ __('worked out when saved') }})</span></div>
</div>
