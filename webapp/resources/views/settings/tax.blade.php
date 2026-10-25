@extends('layouts.settings')
@section('title', __('Tax'))
@section('settings')
@php
  use App\Modules\Tax\Models\TaxCode;
  use App\Modules\Tax\Models\TaxRegistration;
  use App\Modules\Tax\Services\ServiceClasses;
  $india = $company->country === 'IN';
  $label = $india ? 'GSTIN' : 'TRN';
  $money = fn ($v, $c = null) => \App\Support\Money::format($v, $c ?? $company->currency);
  $catTone = ['S' => 'bg-sky-50 text-sent', 'Z' => 'bg-emerald-50 text-paid', 'E' => 'bg-ink-100 text-ink-700', 'AE' => 'bg-amber-50 text-partial', 'O' => 'bg-ink-100 text-ink-500'];
  $t = $trial['input'] ?? [];
@endphp

<section class="card p-6">
  <h2 class="text-xl font-semibold">{{ $india ? __('GST') : __('VAT') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('Rates and rules are data with dates: when the law changes, a new rate is added from its date and older invoices keep theirs. The engine never guesses — a case no rule covers stops the invoice with a message.') }}</p>
  <p class="mt-3 flex gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"><x-icon name="alert" class="size-4 shrink-0" /> {{ __('The rates and rules below are our reading of the law in October 2026. Items marked “verify” must be checked by your tax adviser before the first invoice.') }}</p>
</section>

{{-- Our registrations --}}
<section class="card p-6" id="registrations">
  <h3 class="text-lg font-semibold">{{ __('Our registrations') }}</h3>
  <p class="mt-1 text-sm text-ink-500">{{ $india ? __('One GSTIN per state you bill from. Each has its own GTA option for the year (Annexure V).') : __('Your TRN. Invoices show it with the emirate.') }}</p>

  <div class="mt-4 space-y-4">
    @forelse ($registrations as $reg)
      @php($year = $reg->years->firstWhere('fy_key', $fy->key))
      <article class="rounded-2xl border border-ink-100 p-4">
        <div class="flex flex-wrap items-center gap-3">
          <span class="docno font-mono text-base font-semibold">{{ $reg->tax_id }}</span>
          <span class="text-sm text-ink-500">{{ $regions[$reg->state_code] ?? $reg->state_code }}</span>
          @if ($reg->is_default)<span class="pill bg-brand-50 text-brand-700">{{ __('Default') }}</span>@endif
          @if ($reg->cancelled_on)<span class="pill bg-red-50 text-overdue">{{ __('Cancelled from :date', ['date' => $reg->cancelled_on->format('d M Y')]) }}</span>@endif
          <span class="ms-auto text-sm text-ink-700">{{ $reg->legal_name }}</span>
        </div>

        @if ($reg->regime === 'in_gst')
          <div class="mt-3 grid gap-3 lg:grid-cols-2">
            <div class="text-sm">
              <div class="font-medium">{{ __('GTA option') }}</div>
              <ul class="mt-1 space-y-0.5">
                @forelse ($reg->years as $y)
                  <li><span class="font-mono text-xs">{{ $y->fy_key }}</span> · {{ __(TaxRegistration::GTA_OPTIONS[$y->gta_option]) }}@if ($y->annexure_ref) <span class="text-xs text-ink-500">· {{ $y->annexure_ref }}</span>@endif</li>
                @empty
                  <li class="text-ink-500">{{ __('None recorded: 5% under reverse charge applies (the default).') }}</li>
                @endforelse
              </ul>
            </div>
            @unless ($year)
              <form method="POST" action="{{ route('settings.tax.years.store', $reg) }}" class="grid gap-2 sm:grid-cols-2" novalidate>
                @csrf
                <input type="hidden" name="fy_key" value="{{ $fy->key }}">
                <select name="gta_option" class="input py-2 sm:col-span-2" aria-label="{{ __('GTA option for :fy', ['fy' => $fy->key]) }}">
                  @foreach (TaxRegistration::GTA_OPTIONS as $v => $text)<option value="{{ $v }}">{{ $fy->key }}: {{ __($text) }}</option>@endforeach
                </select>
                <input name="annexure_ref" class="input py-2" maxlength="60" placeholder="{{ __('Annexure V ref. (optional)') }}" aria-label="{{ __('Annexure V reference') }}">
                <button class="btn-secondary">{{ __('Record for :fy', ['fy' => $fy->key]) }}</button>
                <p class="text-xs text-ink-500 sm:col-span-2">{{ __('Locked for the year once recorded.') }}</p>
              </form>
            @endunless
          </div>
        @endif

        <details class="mt-3">
          <summary class="cursor-pointer text-sm font-medium text-brand-700">{{ __('Change details') }}</summary>
          <form method="POST" action="{{ route('settings.tax.registrations.update', $reg) }}" class="mt-3 grid gap-3 sm:grid-cols-2" novalidate>
            @csrf @method('PUT')
            <div><label class="label" for="ln{{ $reg->id }}">{{ __('Legal name') }}</label><input id="ln{{ $reg->id }}" name="legal_name" class="input" maxlength="200" value="{{ $reg->legal_name }}"></div>
            <div><label class="label" for="co{{ $reg->id }}">{{ __('Cancelled from') }}</label><input id="co{{ $reg->id }}" type="date" name="cancelled_on" class="input" value="{{ $reg->cancelled_on?->toDateString() }}"></div>
            <div class="sm:col-span-2"><label class="label" for="ad{{ $reg->id }}">{{ __('Address on the registration') }}</label><textarea id="ad{{ $reg->id }}" name="address" rows="2" class="input" maxlength="1000">{{ $reg->address }}</textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="size-4 rounded border-ink-300" @checked($reg->is_default)> {{ __('Default for new invoices') }}</label>
            <div class="text-end"><button class="btn-secondary">{{ __('Save') }}</button></div>
          </form>
        </details>
      </article>
    @empty
      <p class="rounded-xl bg-ink-50 px-4 py-3 text-sm text-ink-700">{{ $india ? __('No GSTIN yet: invoices are issued as bills of supply, without GST.') : __('No TRN yet: invoices are issued without VAT.') }}</p>
    @endforelse
  </div>

  <form method="POST" action="{{ route('settings.tax.registrations.store') }}" class="mt-5 grid gap-3 border-t border-ink-100 pt-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate>
    @csrf
    <div><label class="label" for="new_tax_id">{{ __('Add a :label', ['label' => $label]) }}</label><input id="new_tax_id" name="tax_id" class="input font-mono uppercase" maxlength="20" value="{{ old('tax_id') }}" placeholder="{{ $india ? '32AABCS1234F1Z5' : '100123456700003' }}"></div>
    @if ($india)
      <div><label class="label" for="new_legal">{{ __('Legal name on it') }}</label><input id="new_legal" name="legal_name" class="input" maxlength="200" value="{{ old('legal_name', $company->legal_name) }}"></div>
    @else
      <div><label class="label" for="new_state">{{ __('Emirate') }}</label><select id="new_state" name="state_code" class="input">@foreach ($regions as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></div>
    @endif
    <button class="btn-primary"><x-icon name="plus" /> {{ __('Add') }}</button>
    @error('tax_id')<p class="error sm:col-span-3">{{ $message }}</p>@enderror
    @error('gta_option')<p class="error sm:col-span-3">{{ $message }}</p>@enderror
  </form>
</section>

{{-- Invoice rules --}}
<form method="POST" action="{{ route('settings.tax.rules') }}" class="card p-6" novalidate>
  @csrf @method('PUT')
  <h3 class="text-lg font-semibold">{{ __('Invoice rules') }}</h3>
  <div class="mt-3 divide-y divide-ink-100 text-sm">
    <label class="flex items-center justify-between gap-4 py-3"><span>{{ __(\App\Modules\Settings\Services\CompanySettings::DEFAULTS['invoice_round_total'][2]) }}</span><input type="checkbox" name="invoice_round_total" value="1" class="size-5 rounded border-ink-300" @checked($values['invoice_round_total'])></label>
    <div class="flex flex-wrap items-center justify-between gap-4 py-3">
      <label for="evidence_policy">{{ __('Zero-rated lines without their papers') }}</label>
      <select id="evidence_policy" name="tax_evidence_policy" class="input w-72">
        <option value="block" @selected($values['tax_evidence_policy'] === 'block')>{{ __('Block the invoice until they are on file') }}</option>
        <option value="warn" @selected($values['tax_evidence_policy'] === 'warn')>{{ __('Warn and mark it “pending evidence”') }}</option>
      </select>
    </div>
    @if ($india)
      <label class="flex items-center justify-between gap-4 py-3"><span>{{ __(\App\Modules\Settings\Services\CompanySettings::DEFAULTS['tds_declaration_given'][2]) }}</span><input type="checkbox" name="tds_declaration_given" value="1" class="size-5 rounded border-ink-300" @checked($values['tds_declaration_given'])></label>
    @endif
  </div>
  <div class="mt-3 text-end"><button class="btn-secondary"><x-icon name="check" /> {{ __('Save') }}</button></div>
</form>

{{-- Try a case --}}
<section class="card p-6" id="try">
  <h3 class="text-lg font-semibold">{{ __('Try a case') }}</h3>
  <p class="mt-1 text-sm text-ink-500">{{ __('Work out the tax of one charge, as an invoice would, with the reasons.') }}</p>
  <form method="GET" action="{{ route('settings.tax') }}#try" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" novalidate>
    <input type="hidden" name="try" value="1">
    <div><label class="label" for="t_reg">{{ __('Our registration') }}</label>
      <select id="t_reg" name="registration_id" class="input"><option value="">{{ __('Not registered') }}</option>@foreach ($registrations as $reg)<option value="{{ $reg->id }}" @selected((int) ($t['registration_id'] ?? ($registrations->firstWhere('is_default', true)?->id)) === $reg->id)>{{ $reg->tax_id }}</option>@endforeach</select></div>
    <div><label class="label" for="t_party">{{ __('Client') }}</label>
      <select id="t_party" name="party_id" class="input">@foreach ($parties as $p)<option value="{{ $p->id }}" @selected((int) ($t['party_id'] ?? 0) === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label class="label" for="t_class">{{ __('Charge') }}</label>
      <select id="t_class" name="service_class" class="input">@foreach (ServiceClasses::CLASSES as $c => $name)<option value="{{ $c }}" @selected(($t['service_class'] ?? 'FREIGHT_ROAD') === $c)>{{ __($name) }}</option>@endforeach</select></div>
    <div><label class="label" for="t_amount">{{ __('Amount') }}</label><input id="t_amount" name="amount" type="number" min="0.01" step="0.01" class="input" value="{{ $t['amount'] ?? 50000 }}"></div>
    <div><label class="label" for="t_date">{{ __('Invoice date') }}</label><input id="t_date" name="date" type="date" class="input" value="{{ $t['date'] ?? now()->toDateString() }}"></div>
    @if ($india)
      <div><label class="label" for="t_load">{{ __('Loading state (unregistered client)') }}</label>
        <select id="t_load" name="loading_state" class="input"><option value="">—</option>@foreach ($regions as $code => $name)<option value="{{ $code }}" @selected(($t['loading_state'] ?? null) === $code)>{{ $code }} · {{ $name }}</option>@endforeach</select></div>
    @else
      <div><label class="label" for="t_type">{{ __('Transport') }}</label>
        <select id="t_type" name="transport_type" class="input"><option value="">—</option>@foreach (['domestic' => __('Domestic'), 'export' => __('Export'), 'import' => __('Import'), 'international' => __('International')] as $v => $n)<option value="{{ $v }}" @selected(($t['transport_type'] ?? null) === $v)>{{ $n }}</option>@endforeach</select></div>
      <div><label class="label" for="t_role">{{ __('We are') }}</label>
        <select id="t_role" name="carrier_role" class="input"><option value="main" @selected(($t['carrier_role'] ?? 'main') === 'main')>{{ __('The main carrier') }}</option><option value="sub" @selected(($t['carrier_role'] ?? null) === 'sub')>{{ __('A subcontractor') }}</option></select></div>
    @endif
    <fieldset class="sm:col-span-2 xl:col-span-4">
      <legend class="label">{{ __('Papers on file') }}</legend>
      <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
        @foreach (TaxCode::EVIDENCE as $e => $name)<label class="flex items-center gap-1.5"><input type="checkbox" name="evidence[]" value="{{ $e }}" class="size-4 rounded border-ink-300" @checked(in_array($e, $t['evidence'] ?? [], true))> {{ __($name) }}</label>@endforeach
        <label class="flex items-center gap-1.5"><input type="checkbox" name="inclusive" value="1" class="size-4 rounded border-ink-300" @checked(request()->boolean('inclusive'))> {{ __('The amount includes tax') }}</label>
      </div>
    </fieldset>
    <div class="sm:col-span-2 xl:col-span-4"><button class="btn-primary"><x-icon name="sparkle" /> {{ __('Work it out') }}</button></div>
    @foreach (['party_id', 'amount', 'date', 'service_class'] as $f)@error($f)<p class="error sm:col-span-2">{{ $message }}</p>@enderror @endforeach
  </form>

  @if ($trial)
    @if ($trial['error'])
      <p class="mt-5 flex gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-overdue"><x-icon name="alert" class="size-4 shrink-0" /> {{ $trial['error'] }}</p>
    @else
      @php($r = $trial['result'])
      <div class="mt-5 grid gap-4 rounded-2xl border border-ink-100 p-5 lg:grid-cols-2" data-tax-result>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="pill bg-ink-100 text-ink-700">{{ ['tax_invoice' => __('Tax invoice'), 'bill_of_supply' => __('Bill of supply'), 'invoice' => __('Invoice without tax')][$r['document']] }}</span>
            @if ($r['reverse_charge'])<span class="pill bg-amber-50 text-partial">{{ __('Reverse charge') }}</span>@endif
            @if ($r['pending_evidence'])<span class="pill bg-red-50 text-overdue">{{ __('Pending evidence') }}</span>@endif
            @foreach ($r['lines'] as $line)<span class="pill {{ $catTone[$line['category'] ?? 'O'] ?? 'bg-ink-100' }}">{{ $line['tax_code_name'] ?? __('No tax') }}</span>@if (! empty($line['sac_code']))<span class="font-mono text-xs text-ink-500">{{ $india ? 'SAC' : '' }} {{ $line['sac_code'] }}</span>@endif @endforeach
          </div>
          <dl class="mt-4 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-ink-500">{{ __('Taxable value') }}</dt><dd class="money">{{ $money($r['taxable_total'], $r['currency']) }}</dd></div>
            @foreach ($r['taxes'] as $tax)
              <div class="flex justify-between {{ $tax['charged'] ? '' : 'text-ink-500' }}"><dt>{{ $tax['label'] }}{{ $tax['charged'] ? '' : ' · ' . __('paid by the client (reverse charge)') }}</dt><dd class="money">{{ $money($tax['amount'], $r['currency']) }}</dd></div>
            @endforeach
            @if ($r['round_off'] != 0)<div class="flex justify-between text-ink-500"><dt>{{ __('Round off') }}</dt><dd class="money">{{ $money($r['round_off'], $r['currency']) }}</dd></div>@endif
            <div class="flex justify-between border-t border-ink-100 pt-1.5 font-semibold"><dt>{{ __('Invoice total') }}</dt><dd class="money">{{ $money($r['total'], $r['currency']) }}</dd></div>
            @if ($r['withholding'])
              <div class="flex justify-between text-ink-700"><dt>{{ __('TDS the client deducts (:section, :rate%)', ['section' => $r['withholding']['label'], 'rate' => rtrim(rtrim(number_format($r['withholding']['rate'], 2), '0'), '.')]) }}</dt><dd class="money">− {{ $money($r['withholding']['amount'], $r['currency']) }}</dd></div>
            @endif
          </dl>
          @foreach ($r['legal_texts'] as $text)<p class="mt-3 rounded-lg bg-ink-50 px-3 py-2 text-xs text-ink-700">“{{ $text }}”</p>@endforeach
          @foreach ($r['warnings'] as $w)<p class="mt-2 text-xs text-overdue">{{ $w }}</p>@endforeach
        </div>
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ __('Why') }}</h4>
          <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm text-ink-700">@foreach ($r['trace'] as $step)<li>{{ $step }}</li>@endforeach</ol>
        </div>
      </div>
    @endif
  @endif
</section>

{{-- Codes and rates --}}
<section class="card overflow-hidden" id="codes">
  <div class="p-6 pb-3"><h3 class="text-lg font-semibold">{{ __('Tax codes and rates') }}</h3></div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Rates') }}</th><th>{{ __('Printed on the invoice') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($codes as $code)
          <tr class="align-top">
            <td><div class="font-medium">{{ $code->name }}</div><div class="docno font-mono text-xs text-ink-500">{{ $code->code }}</div></td>
            <td><span class="pill {{ $catTone[$code->category] ?? 'bg-ink-100' }}">{{ $code->category }} · {{ __(TaxCode::CATEGORIES[$code->category]) }}</span></td>
            <td class="text-sm">
              @foreach ($code->rates as $rate)
                <div class="{{ $rate->valid_to && $rate->valid_to->isPast() ? 'text-ink-400' : '' }}">
                  <span class="font-semibold">{{ $rate->percentLabel() }}</span>
                  <span class="text-xs">{{ __('from :from', ['from' => $rate->valid_from->format('d M Y')]) }}{{ $rate->valid_to ? ' ' . __('to :to', ['to' => $rate->valid_to->format('d M Y')]) : '' }}</span>
                  @if ($rate->parts && ! isset($rate->parts['fixed']))<span class="text-xs text-ink-500">· {{ collect($rate->parts['intra'] ?? [])->map(fn ($p, $c) => $c . ' ' . \App\Modules\Tax\Services\TaxCalculator::pct($p))->join(' + ') }} / {{ collect($rate->parts['inter'] ?? [])->map(fn ($p, $c) => $c . ' ' . \App\Modules\Tax\Services\TaxCalculator::pct($p))->join(' + ') }}</span>@endif
                  @if ($rate->note)<div class="text-xs text-ink-500">{{ $rate->note }}</div>@endif
                </div>
              @endforeach
            </td>
            <td class="max-w-xs text-xs text-ink-700">{{ $code->legal_text }}@if ($code->evidence)<div class="mt-1 text-ink-500">{{ __('Needs: :papers', ['papers' => collect($code->evidence)->map(fn ($e) => __(TaxCode::EVIDENCE[$e] ?? $e))->join(', ')]) }}</div>@endif</td>
            <td class="text-end">
              @if (! in_array($code->category, ['E', 'O'], true))
                <details class="inline-block text-start">
                  <summary class="btn-ghost cursor-pointer list-none px-2 py-1 text-xs">{{ __('New rate') }}</summary>
                  <form method="POST" action="{{ route('settings.tax.rates.store', $code) }}" class="mt-2 grid w-60 gap-2" novalidate>@csrf
                    <input name="rate" type="number" min="0" max="100" step="0.001" class="input py-1.5" placeholder="{{ __('Rate %') }}" aria-label="{{ __('Rate %') }}">
                    <input name="valid_from" type="date" class="input py-1.5" aria-label="{{ __('From') }}">
                    <input name="note" class="input py-1.5" maxlength="255" placeholder="{{ __('Notification / source') }}" aria-label="{{ __('Source') }}">
                    <button class="btn-secondary px-2.5 py-1.5 text-xs">{{ __('Add from that date') }}</button>
                  </form>
                </details>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @foreach (['valid_from', 'rate'] as $f)@error($f)<p class="error px-6 pb-4">{{ $message }}</p>@enderror @endforeach
</section>

{{-- Rules --}}
<section class="card overflow-hidden" id="rules">
  <div class="p-6 pb-3">
    <h3 class="text-lg font-semibold">{{ __('Rules') }}</h3>
    <p class="mt-1 text-sm text-ink-500">{{ __('The rule with the most matching conditions wins, then the higher priority. “Follows the freight” gives an add-on the freight line’s tax.') }}</p>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Charge') }}</th><th>{{ __('When') }}</th><th>{{ __('Tax code') }}</th><th>{{ $india ? 'SAC' : __('Code') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($rules as $rule)
          <tr class="align-top {{ $rule->active ? '' : 'opacity-50' }}">
            <td class="text-sm font-medium">{{ $rule->service_class ? ServiceClasses::label($rule->service_class) : __('Any') }}</td>
            <td class="text-xs text-ink-700">
              {{ collect([
                  $rule->gta_option ? __('GTA option: :o', ['o' => str_replace('_', ' ', $rule->gta_option)]) : null,
                  $rule->transport_type ? __('transport: :t', ['t' => $rule->transport_type]) : null,
                  $rule->carrier_role ? ($rule->carrier_role === 'sub' ? __('we are a subcontractor') : __('we are the main carrier')) : null,
                  $rule->buyer_registered === null ? null : ($rule->buyer_registered ? __('client registered') : __('client not registered')),
                  $rule->buyer_category ? __('client: :c', ['c' => $rule->buyer_category]) : null,
                  $rule->valid_from ? __('from :d', ['d' => $rule->valid_from->format('d M Y')]) : null,
                  $rule->valid_to ? __('to :d', ['d' => $rule->valid_to->format('d M Y')]) : null,
                ])->filter()->join(' · ') ?: __('always') }}
              @if ($rule->note)<div class="text-ink-500">{{ $rule->note }}</div>@endif
            </td>
            <td>
              <select name="tax_code" form="rule{{ $rule->id }}" class="input w-56 py-1.5 text-sm" aria-label="{{ __('Tax code') }}">
                <option value="@main" @selected($rule->tax_code === '@main')>{{ __('Follows the freight') }}</option>
                @foreach ($codes as $code)<option value="{{ $code->code }}" @selected($rule->tax_code === $code->code)>{{ $code->name }}</option>@endforeach
              </select>
            </td>
            <td><input name="sac_code" form="rule{{ $rule->id }}" class="input w-24 py-1.5 font-mono text-sm" maxlength="10" value="{{ $rule->sac_code }}" aria-label="{{ __('SAC') }}"></td>
            <td><input type="checkbox" name="active" value="1" form="rule{{ $rule->id }}" class="size-4 rounded border-ink-300" @checked($rule->active) aria-label="{{ __('On') }}"></td>
            <td class="text-end"><form id="rule{{ $rule->id }}" method="POST" action="{{ route('settings.tax.rule.update', $rule) }}">@csrf @method('PUT')<button class="btn-ghost px-2 py-1 text-xs">{{ __('Save') }}</button></form></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endsection
