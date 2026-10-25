@extends('layouts.app')
@section('title', $challan->number ?? __('Hire challan'))
@section('content')
@php
  $h = $challan;
  $editable = ! $h->exists || $h->status === 'draft';
  $v = fn ($f) => old($f, $h->$f instanceof \Carbon\CarbonInterface ? $h->$f->toDateString() : $h->$f);
@endphp
<div class="flex flex-wrap items-center gap-3">
  <a href="{{ route('trips.show', $trip) }}" class="btn-ghost"><x-icon name="arrow-left" /> <span class="docno font-mono">{{ $trip->label }}</span></a>
  @if ($h->exists)<span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Trips\Models\HireChallan::STATUSES[$h->status]) }}</span>@endif
  @error('challan')<span class="error">{{ $message }}</span>@enderror
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
  <form method="POST" action="{{ $h->exists ? route('trips.hire.update', [$trip, $h]) : route('trips.hire.store', $trip) }}" class="space-y-6" novalidate>
    @csrf
    @if ($h->exists) @method('PUT') @endif
    <fieldset @disabled(! $editable) class="space-y-6">
      <section class="card p-6">
        <h2 class="text-lg font-semibold">{{ $h->number ? __('Lorry hire challan :no', ['no' => $h->number]) : __('Lorry hire challan') }}</h2>
        <p class="mt-1 text-sm text-ink-500">{{ $trip->supplier->name }}{{ $trip->supplier->pan ? ' · PAN ' . $trip->supplier->pan : '' }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-4">
          <div><label class="label" for="h_date">{{ __('Date') }}</label><input id="h_date" name="challan_date" type="date" class="input" value="{{ $v('challan_date') }}"></div>
          <div><label class="label" for="h_reg">{{ __('Vehicle') }}</label><input id="h_reg" name="vehicle_reg" class="input font-mono uppercase" value="{{ $v('vehicle_reg') }}" maxlength="20"></div>
          <div><label class="label" for="h_driver">{{ __('Driver') }}</label><input id="h_driver" name="driver_name" class="input" value="{{ $v('driver_name') }}" maxlength="120"></div>
          <div><label class="label" for="h_mobile">{{ __('Driver mobile') }}</label><input id="h_mobile" name="driver_mobile" class="input" value="{{ $v('driver_mobile') }}" maxlength="20"></div>
          <div><label class="label" for="h_lic">{{ __('Licence') }}</label><input id="h_lic" name="driver_licence" class="input" value="{{ $v('driver_licence') }}" maxlength="30"></div>
          <div>
            <label class="label" for="h_basis">{{ __('Hire') }}</label>
            <select id="h_basis" name="hire_basis" class="input"><option value="fixed" @selected($v('hire_basis') === 'fixed')>{{ __('Fixed amount') }}</option><option value="tonne" @selected($v('hire_basis') === 'tonne')>{{ __('Per tonne') }}</option></select>
          </div>
          <div><label class="label" for="h_amount">{{ __('Hire amount') }}</label><input id="h_amount" name="hire_amount" type="number" min="1" step="0.01" class="input" value="{{ $v('hire_amount') }}">@error('hire_amount')<span class="error">{{ $message }}</span>@enderror</div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="label" for="h_rate">{{ __('Rate / MT') }}</label><input id="h_rate" name="hire_rate" type="number" min="0" step="0.01" class="input" value="{{ $v('hire_rate') }}"></div>
            <div><label class="label" for="h_weight">{{ __('MT') }}</label><input id="h_weight" name="hire_weight_mt" type="number" min="0" step="0.001" class="input" value="{{ $v('hire_weight_mt') }}"></div>
          </div>
          <div>
            <label class="label" for="h_broker">{{ __('Broker') }}</label>
            <select id="h_broker" name="broker_party_id" class="input"><option value="">—</option>@foreach ($brokers as $id => $name)<option value="{{ $id }}" @selected((int) $v('broker_party_id') === $id)>{{ $name }}</option>@endforeach</select>
          </div>
          <div><label class="label" for="h_comm">{{ __('Commission') }}</label><input id="h_comm" name="commission_amount" type="number" min="0" step="0.01" class="input" value="{{ $v('commission_amount') ?: '' }}"></div>
          <div>
            <label class="label" for="h_cmode">{{ __('Commission') }}</label>
            <select id="h_cmode" name="commission_mode" class="input"><option value="deducted" @selected($v('commission_mode') !== 'paid')>{{ __('Taken from the hire') }}</option><option value="paid" @selected($v('commission_mode') === 'paid')>{{ __('Paid by us') }}</option></select>
          </div>
          <div><label class="label" for="h_office">{{ __('Office charge') }}</label><input id="h_office" name="office_charge" type="number" min="0" step="0.01" class="input" value="{{ $v('office_charge') ?: '' }}"></div>
          <div><label class="label" for="h_tdss">{{ __('TDS section') }}</label><input id="h_tdss" name="tds_section" class="input" value="{{ $v('tds_section') }}" maxlength="10"></div>
          <div><label class="label" for="h_tdsr">{{ __('TDS %') }}</label><input id="h_tdsr" name="tds_rate" type="number" min="0" max="30" step="0.01" class="input" value="{{ $v('tds_rate') }}"></div>
          <div class="sm:col-span-2"><label class="label" for="h_nil">{{ __('No TDS because') }}</label><input id="h_nil" name="tds_nil_reason" class="input" value="{{ $v('tds_nil_reason') }}" maxlength="150"></div>
          <div><label class="label" for="h_days">{{ __('Balance within (days after POD)') }}</label><input id="h_days" name="balance_days_after_pod" type="number" min="0" class="input" value="{{ $v('balance_days_after_pod') }}"></div>
          <div class="sm:col-span-4"><label class="label" for="h_terms">{{ __('Terms') }}</label><textarea id="h_terms" name="terms" rows="2" class="input" maxlength="2000" placeholder="{{ __('Shortage and damage are deducted. POD within 7 days of delivery.') }}">{{ $v('terms') }}</textarea></div>
        </div>
        <p class="hint">{{ __('TDS is a suggestion (section 194C: 1% individuals/HUF, 2% others; nil with the 10-trucks declaration for the year). Confirm with your accountant.') }}</p>
      </section>
    </fieldset>
    @if ($editable)
      <div class="flex justify-end gap-2"><button class="btn-secondary"><x-icon name="check" /> {{ __('Save draft') }}</button></div>
    @endif
  </form>

  @if ($h->exists)
    <div class="space-y-6">
      <section class="card p-6">
        <h3 class="font-semibold">{{ __('Money') }}</h3>
        <dl class="mt-3 space-y-1.5 text-sm">
          <div class="flex justify-between"><dt>{{ __('Hire') }}</dt><dd class="money">{{ \App\Support\Money::company($h->hire_amount) }}</dd></div>
          @if ((float) $h->commission_amount && $h->commission_mode === 'deducted')<div class="flex justify-between text-ink-500"><dt>{{ __('Commission taken') }}</dt><dd class="money">− {{ \App\Support\Money::company($h->commission_amount) }}</dd></div>@endif
          @if ((float) $h->office_charge)<div class="flex justify-between text-ink-500"><dt>{{ __('Office charge') }}</dt><dd class="money">− {{ \App\Support\Money::company($h->office_charge) }}</dd></div>@endif
          @if ((float) $h->tds_amount)<div class="flex justify-between text-ink-500"><dt>{{ __('TDS :rate%', ['rate' => rtrim(rtrim((string) $h->tds_rate, '0'), '.')]) }}</dt><dd class="money">− {{ \App\Support\Money::company($h->tds_amount) }}</dd></div>@elseif ($h->tds_nil_reason)<div class="text-xs text-ink-500">{{ __('No TDS: :reason', ['reason' => $h->tds_nil_reason]) }}</div>@endif
          @foreach ($h->lines as $l)
            @php([$label, $sign] = \App\Modules\Trips\Models\HireChallan::LINE_KINDS[$l->kind])
            <div class="flex justify-between {{ $sign < 0 ? 'text-ink-500' : '' }}"><dt>{{ __($label) }} <span class="text-xs">{{ $l->line_date->format('d M') }}{{ $l->note ? ' · ' . $l->note : '' }}</span></dt><dd class="money">{{ $sign < 0 ? '−' : '+' }} {{ \App\Support\Money::company($l->amount) }}</dd></div>
          @endforeach
          <div class="flex justify-between border-t border-ink-100 pt-2 text-base font-semibold"><dt>{{ __('Balance') }}</dt><dd class="money">{{ \App\Support\Money::company($h->balance()) }}</dd></div>
        </dl>
      </section>

      @if ($h->status === 'draft')
        <form method="POST" action="{{ route('trips.hire.issue', [$trip, $h]) }}" class="card p-6" data-confirm="{{ __('Issue the challan? The hire is then fixed and numbered.') }}">
          @csrf
          <p class="text-sm text-ink-500">{{ __('Issuing gives the challan its number. After that the hire does not change; detention, deductions and payments are added below.') }}</p>
          <button class="btn-primary mt-3 w-full">{{ __('Issue challan') }}</button>
        </form>
      @elseif ($h->status === 'issued')
        <form method="POST" action="{{ route('trips.hire.lines.store', [$trip, $h]) }}" class="card space-y-3 p-6" novalidate>
          @csrf
          <h3 class="font-semibold">{{ __('Add money') }}</h3>
          <select name="kind" class="input" aria-label="{{ __('Kind') }}">@foreach (\App\Modules\Trips\Models\HireChallan::LINE_KINDS as $v2 => [$label])<option value="{{ $v2 }}">{{ __($label) }}</option>@endforeach</select>
          <div class="grid grid-cols-2 gap-2">
            <input name="amount" type="number" min="1" step="0.01" class="input" placeholder="{{ __('Amount') }}" aria-label="{{ __('Amount') }}">
            <input name="line_date" type="date" class="input" value="{{ now()->toDateString() }}" aria-label="{{ __('Date') }}">
          </div>
          <div class="grid grid-cols-2 gap-2">
            <select name="mode" class="input" aria-label="{{ __('Paid by') }}"><option value="">{{ __('Paid by…') }}</option><option value="cash">{{ __('Cash') }}</option><option value="upi">UPI</option><option value="bank">{{ __('Bank') }}</option></select>
            <select name="paid_to" class="input" aria-label="{{ __('Paid to') }}"><option value="">{{ __('Paid to…') }}</option><option value="owner">{{ __('Owner') }}</option><option value="broker">{{ __('Broker') }}</option><option value="driver">{{ __('Driver') }}</option></select>
          </div>
          <input name="reference" class="input" placeholder="{{ __('UTR / slip no.') }}" maxlength="60" aria-label="{{ __('Reference') }}">
          <input name="note" class="input" placeholder="{{ __('Note (needed for a deduction)') }}" maxlength="255" aria-label="{{ __('Note') }}">
          @foreach (['amount', 'mode', 'note', 'kind', 'line_date'] as $f)@error($f)<span class="error">{{ $message }}</span>@enderror @endforeach
          <button class="btn-secondary w-full">{{ __('Record') }}</button>
        </form>
      @endif
    </div>
  @endif
</div>
@endsection
