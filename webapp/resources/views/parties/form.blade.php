@extends('layouts.app')
@section('title', $party->exists ? __('Edit :name', ['name' => $party->name]) : __('Add party'))
@section('content')
@php
  $p = $party;
  $checked = old('roles', $roles);
  $showFor = fn (array $for) => array_intersect($for, $checked) ? '' : 'hidden';
  $taxLabel = \App\Modules\Parties\Services\TaxIds::label($country);
@endphp
<form method="POST" action="{{ $p->exists ? route('parties.update', $p) : route('parties.store') }}" class="mx-auto max-w-4xl space-y-6" data-party-form data-code-suggest="{{ route('parties.suggest') }}" novalidate>
  @csrf
  @if ($p->exists) @method('PUT') @endif

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Who they are') }}</h2>
    <fieldset class="mt-4">
      <legend class="label">{{ __('Roles') }} <span class="font-normal text-ink-500">— {{ __('one party can have several') }}</span></legend>
      <div class="grid gap-2 sm:grid-cols-3">
        @foreach (\App\Modules\Parties\Models\Party::ROLES as $code => [$label, $hint])
          <label class="flex items-start gap-3 rounded-xl border border-ink-100 px-3 py-2.5 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
            <input type="checkbox" name="roles[]" value="{{ $code }}" class="mt-0.5 size-4 rounded border-ink-300" data-role @checked(in_array($code, $checked, true))>
            <span><span class="font-semibold">{{ __($label) }}</span><span class="block text-xs text-ink-500">{{ __($hint) }}</span></span>
          </label>
        @endforeach
      </div>
      @error('roles')<span class="error">{{ $message }}</span>@enderror
    </fieldset>

    <div class="mt-5 grid gap-4 sm:grid-cols-6">
      <div class="sm:col-span-3">
        <label class="label" for="name">{{ __('Name') }}</label>
        <input id="name" name="name" class="input" value="{{ old('name', $p->name) }}" maxlength="150" required data-suggest-name>
        @error('name')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="sm:col-span-2">
        <label class="label" for="legal_name">{{ __('Legal name') }}</label>
        <input id="legal_name" name="legal_name" class="input" value="{{ old('legal_name', $p->legal_name) }}" maxlength="200">
      </div>
      <div>
        <label class="label" for="short_code">{{ __('Code') }}</label>
        <input id="short_code" name="short_code" class="input uppercase" value="{{ old('short_code', $p->short_code) }}" maxlength="6" data-suggest-code @if ($p->short_code) data-touched="1" @endif>
        @error('short_code')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
    <p class="hint">{{ __('The code goes into trip numbers when this party is the shipper (261025-ACC-COK-BOM-01).') }}</p>

    @if ($groups->isNotEmpty())
      <div class="mt-4 max-w-sm">
        <label class="label" for="group_party_id">{{ __('Part of a group') }}</label>
        <select id="group_party_id" name="group_party_id" class="input">
          <option value="">—</option>
          @foreach ($groups as $g)<option value="{{ $g->id }}" @selected((int) old('group_party_id', $p->group_party_id) === $g->id)>{{ $g->name }}</option>@endforeach
        </select>
        <span class="hint">{{ __('For example every plant of one cement company.') }}</span>
      </div>
    @endif
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Contact and address') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <div>
        <label class="label" for="mobile">{{ __('Mobile') }}</label>
        <input id="mobile" name="mobile" class="input" value="{{ old('mobile', $p->mobile) }}" maxlength="20" inputmode="tel">
        @error('mobile')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="email">{{ __('Email') }}</label>
        <input id="email" name="email" type="email" class="input" value="{{ old('email', $p->email) }}" maxlength="255">
        @error('email')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div><label class="label" for="address_line1">{{ __('Address') }}</label><input id="address_line1" name="address_line1" class="input" value="{{ old('address_line1', $p->address_line1) }}" maxlength="200"></div>
      <div><label class="label" for="address_line2">{{ __('Address line 2') }}</label><input id="address_line2" name="address_line2" class="input" value="{{ old('address_line2', $p->address_line2) }}" maxlength="200"></div>
      <div><label class="label" for="city">{{ __('City') }}</label><input id="city" name="city" class="input" value="{{ old('city', $p->city) }}" maxlength="100"></div>
      <div class="grid grid-cols-[1fr_9rem] gap-3">
        <div>
          <label class="label" for="state_code">{{ \App\Support\Regions::label($country) }}</label>
          <select id="state_code" name="state_code" class="input">
            <option value="">—</option>
            @foreach ($regions as $code => $region)<option value="{{ $code }}" @selected(old('state_code', $p->state_code) === (string) $code)>{{ $region }}</option>@endforeach
          </select>
        </div>
        <div><label class="label" for="postal_code">{{ $country === 'IN' ? __('PIN code') : __('P.O. Box') }}</label><input id="postal_code" name="postal_code" class="input" value="{{ old('postal_code', $p->postal_code) }}" maxlength="20"></div>
      </div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Tax') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      @unless ($p->exists)
        <div>
          <label class="label" for="tax_id">{{ $taxLabel }}</label>
          <input id="tax_id" name="tax_id" class="input font-mono uppercase" value="{{ old('tax_id') }}" maxlength="20">
          <span class="hint">{{ $country === 'IN' ? __('Fills the state and PAN. Add more GSTINs on the party page.') : __('More registrations can be added on the party page.') }}</span>
          @error('tax_id')<span class="error">{{ $message }}</span>@enderror
        </div>
      @endunless
      @if ($country === 'IN')
        <div>
          <label class="label" for="pan">{{ __('PAN') }}</label>
          <input id="pan" name="pan" class="input font-mono uppercase" value="{{ old('pan', $p->pan) }}" maxlength="12">
          @error('pan')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="label" for="tan">{{ __('TAN') }}</label>
          <input id="tan" name="tan" class="input font-mono uppercase" value="{{ old('tan', $p->tan) }}" maxlength="10">
          @error('tan')<span class="error">{{ $message }}</span>@enderror
        </div>
      @endif
    </div>
  </section>

  <section class="card p-6 {{ $showFor(['client']) }}" data-role-section="client">
    <h2 class="text-lg font-semibold">{{ __('Credit and billing') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div><label class="label" for="credit_limit">{{ __('Credit limit') }}</label><input id="credit_limit" name="credit_limit" type="number" min="0" step="0.01" class="input" value="{{ old('credit_limit', $p->credit_limit) }}"></div>
      <div><label class="label" for="credit_days">{{ __('Credit days') }}</label><input id="credit_days" name="credit_days" type="number" min="0" max="365" class="input" value="{{ old('credit_days', $p->credit_days) }}"></div>
      <div>
        <label class="label" for="due_basis">{{ __('Days counted from') }}</label>
        <select id="due_basis" name="due_basis" class="input">
          <option value="invoice" @selected(old('due_basis', $p->due_basis) === 'invoice')>{{ __('Invoice date') }}</option>
          <option value="submission" @selected(old('due_basis', $p->due_basis) === 'submission')>{{ __('Bill submission') }}</option>
        </select>
      </div>
      <div>
        <label class="label" for="credit_action">{{ __('Over the limit') }}</label>
        <select id="credit_action" name="credit_action" class="input">
          @foreach (['warn' => __('Warn'), 'block' => __('Block new trips'), 'override' => __('Manager may override')] as $v => $t)<option value="{{ $v }}" @selected(old('credit_action', $p->credit_action) === $v)>{{ $t }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="label" for="billing_cycle">{{ __('Billing') }}</label>
        <select id="billing_cycle" name="billing_cycle" class="input">
          @foreach (['per_lr' => __('Each LR'), 'weekly' => __('Weekly'), 'fortnightly' => __('Fortnightly'), 'monthly' => __('Monthly')] as $v => $t)<option value="{{ $v }}" @selected(old('billing_cycle', $p->billing_cycle) === $v)>{{ $t }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="label" for="pod_required">{{ __('Bill only with POD') }}</label>
        <select id="pod_required" name="pod_required" class="input">
          @foreach (['none' => __('Not needed'), 'original' => __('Original POD'), 'epod' => __('e-POD is enough')] as $v => $t)<option value="{{ $v }}" @selected(old('pod_required', $p->pod_required) === $v)>{{ $t }}</option>@endforeach
        </select>
      </div>
      <div><label class="label" for="late_interest_rate">{{ __('Late interest % a year') }}</label><input id="late_interest_rate" name="late_interest_rate" type="number" min="0" max="60" step="0.01" class="input" value="{{ old('late_interest_rate', $p->late_interest_rate) }}"></div>
      <div><label class="label" for="vendor_code_at_client">{{ __('Our vendor code with them') }}</label><input id="vendor_code_at_client" name="vendor_code_at_client" class="input" value="{{ old('vendor_code_at_client', $p->vendor_code_at_client) }}" maxlength="40"></div>
    </div>
    <fieldset class="mt-4">
      <legend class="label">{{ __('References they need on bills') }}</legend>
      <div class="flex flex-wrap gap-4 text-sm">
        @foreach (['po' => __('PO number'), 'indent' => __('Indent number'), 'shipment' => __('Shipment number')] as $v => $t)
          <label class="flex items-center gap-2"><input type="checkbox" name="required_refs[]" value="{{ $v }}" class="size-4 rounded border-ink-300" @checked(in_array($v, old('required_refs', $p->required_refs ?? []), true))> {{ $t }}</label>
        @endforeach
      </div>
    </fieldset>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <label class="flex items-center gap-2 self-end pb-3 text-sm sm:col-span-2"><input type="checkbox" name="tds_deducted" value="1" class="size-4 rounded border-ink-300" @checked(old('tds_deducted', $p->tds_deducted))> {{ __('They deduct TDS from our bills') }}</label>
      <label class="flex items-center gap-2 self-end pb-3 text-sm sm:col-span-2"><input type="hidden" name="send_reminders_shown" value="1"><input type="checkbox" name="send_reminders" value="1" class="size-4 rounded border-ink-300" @checked(old('send_reminders', $p->send_reminders ?? true))> {{ __('Email them reminders for overdue bills (when reminders are on)') }}</label>
      <div><label class="label" for="tds_section">{{ __('TDS section') }}</label><input id="tds_section" name="tds_section" class="input" value="{{ old('tds_section', $p->tds_section) }}" maxlength="10" placeholder="194C"></div>
      <div><label class="label" for="tds_rate">{{ __('TDS %') }}</label><input id="tds_rate" name="tds_rate" type="number" min="0" max="30" step="0.01" class="input" value="{{ old('tds_rate', $p->tds_rate) }}"></div>
    </div>
    <div class="mt-4"><label class="label" for="portal_url">{{ __('Their supplier portal') }}</label><input id="portal_url" name="portal_url" type="url" class="input" value="{{ old('portal_url', $p->portal_url) }}" maxlength="255" placeholder="https://"></div>
  </section>

  <section class="card p-6 {{ $showFor(['supplier', 'broker']) }}" data-role-section="supplier broker">
    <h2 class="text-lg font-semibold">{{ __('Hired trucks') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div>
        <label class="label" for="supplier_type">{{ __('Kind of supplier') }}</label>
        <select id="supplier_type" name="supplier_type" class="input">
          <option value="">—</option>
          @foreach (\App\Modules\Parties\Models\Party::SUPPLIER_TYPES as $v => $t)<option value="{{ $v }}" @selected(old('supplier_type', $p->supplier_type) === $v)>{{ __($t) }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="label" for="rating">{{ __('Rating') }}</label>
        <select id="rating" name="rating" class="input">
          <option value="">—</option>
          @foreach ([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}" @selected((int) old('rating', $p->rating) === $r)>{{ str_repeat('★', $r) }}</option>@endforeach
        </select>
      </div>
    </div>
  </section>

  <section class="card p-6 {{ $showFor(['vendor']) }}" data-role-section="vendor">
    <h2 class="text-lg font-semibold">{{ __('Vendor') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div>
        <label class="label" for="vendor_category">{{ __('Sells') }}</label>
        <select id="vendor_category" name="vendor_category" class="input">
          <option value="">—</option>
          @foreach (\App\Modules\Parties\Models\Party::VENDOR_CATEGORIES as $v => $t)<option value="{{ $v }}" @selected(old('vendor_category', $p->vendor_category) === $v)>{{ __($t) }}</option>@endforeach
        </select>
      </div>
      @if ($country === 'IN')
        <div>
          <label class="label" for="udyam_number">{{ __('Udyam (MSME) number') }}</label>
          <input id="udyam_number" name="udyam_number" class="input font-mono uppercase" value="{{ old('udyam_number', $p->udyam_number) }}" maxlength="25" placeholder="UDYAM-KL-01-1234567">
          @error('udyam_number')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="label" for="udyam_type">{{ __('MSME size') }}</label>
          <select id="udyam_type" name="udyam_type" class="input">
            <option value="">—</option>
            @foreach (['micro' => __('Micro'), 'small' => __('Small'), 'medium' => __('Medium')] as $v => $t)<option value="{{ $v }}" @selected(old('udyam_type', $p->udyam_type) === $v)>{{ $t }}</option>@endforeach
          </select>
          <span class="hint">{{ __('Micro and small vendors must be paid within 45 days.') }}</span>
        </div>
      @endif
      <div>
        <label class="label" for="lat">{{ __('Pump location (latitude)') }}</label>
        <input id="lat" name="lat" class="input" value="{{ old('lat', $p->lat) }}" inputmode="decimal">
        @error('lat')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="lng">{{ __('Longitude') }}</label>
        <input id="lng" name="lng" class="input" value="{{ old('lng', $p->lng) }}" inputmode="decimal">
        @error('lng')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6 {{ $showFor(['supplier', 'vendor', 'broker']) }}" data-role-section="supplier vendor broker">
    <div class="max-w-xs">
      <label class="label" for="payment_days">{{ __('We pay them within (days)') }}</label>
      <input id="payment_days" name="payment_days" type="number" min="0" max="365" class="input" value="{{ old('payment_days', $p->payment_days) }}">
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Opening balance') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('What was due when you started using this app (from Tally, Excel or your books).') }}</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="opening_balance">{{ __('Amount') }}</label><input id="opening_balance" name="opening_balance" type="number" min="0" step="0.01" class="input" value="{{ old('opening_balance', $p->opening_balance ?: '') }}"></div>
      <div>
        <label class="label" for="opening_side">{{ __('Who owes') }}</label>
        <select id="opening_side" name="opening_side" class="input">
          <option value="receivable" @selected(old('opening_side', $p->opening_side) === 'receivable')>{{ __('They owe us') }}</option>
          <option value="payable" @selected(old('opening_side', $p->opening_side) === 'payable')>{{ __('We owe them') }}</option>
        </select>
      </div>
      <div>
        <label class="label" for="opening_as_of">{{ __('As on') }}</label>
        <input id="opening_as_of" name="opening_as_of" type="date" class="input" value="{{ old('opening_as_of', $p->opening_as_of?->toDateString()) }}">
        @error('opening_as_of')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
  </section>

  <section class="card p-6">
    <label class="label" for="notes">{{ __('Notes') }}</label>
    <textarea id="notes" name="notes" rows="3" class="input" maxlength="2000">{{ old('notes', $p->notes) }}</textarea>
    <div class="mt-4 flex flex-wrap gap-6 text-sm">
      <label class="flex items-center gap-2"><input type="checkbox" name="blacklisted" value="1" class="size-4 rounded border-ink-300" @checked(old('blacklisted', $p->blacklisted))> {{ __('Blacklisted (do not use)') }}</label>
      @if ($p->exists)
        <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" class="size-4 rounded border-ink-300" @checked(old('active', $p->active))> {{ __('In use') }}</label>
      @endif
    </div>
    <div class="mt-3"><input name="blacklist_reason" class="input" value="{{ old('blacklist_reason', $p->blacklist_reason) }}" maxlength="255" placeholder="{{ __('Reason for the blacklist') }}" aria-label="{{ __('Reason for the blacklist') }}"></div>
    @error('blacklist_reason')<span class="error">{{ $message }}</span>@enderror
  </section>

  <div class="flex justify-end gap-2">
    <a href="{{ $p->exists ? route('parties.show', $p) : route('parties.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ $p->exists ? __('Save') : __('Add party') }}</button>
  </div>
</form>
@endsection
