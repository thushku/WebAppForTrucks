@extends('layouts.app')
@section('title', __('Payment voucher'))
@section('content')
@include('accounts._nav')
<form method="POST" action="{{ route('accounts.payments.store') }}" class="card grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-3" novalidate>@csrf
  <div class="sm:col-span-2 xl:col-span-3">
    <h2 class="text-lg font-semibold">{{ __('Payment voucher') }}</h2>
    <p class="text-sm text-ink-500">{{ __('Money paid to a vendor (the pump’s monthly bill, the workshop), a truck supplier, a crew member’s bata or salary, or an expense — from cash or the bank. Hire challan advances and balances are paid on the challan.') }}</p>
  </div>
  <div class="sm:col-span-2"><label class="label" for="pv_payee">{{ __('Paid to') }}</label>
    <select id="pv_payee" name="ledger_account_id" class="input" required>
      <option value="">{{ __('Choose') }}</option>
      @foreach ($payees->groupBy(fn ($l) => $l->group->name) as $group => $items)
        <optgroup label="{{ $group }}">@foreach ($items as $l)<option value="{{ $l->id }}" @selected((int) old('ledger_account_id', $payee) === $l->id)>{{ $l->name }}</option>@endforeach</optgroup>
      @endforeach
    </select>@error('ledger_account_id')<span class="error">{{ $message }}</span>@enderror
    <span class="hint">{{ __('A crew member is paid from their earnings (bata, salary).') }}</span></div>
  <div><label class="label" for="pv_amount">{{ __('Amount') }}</label><input id="pv_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" class="input" required>@error('amount')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="pv_date">{{ __('Paid on') }}</label><input id="pv_date" type="date" name="paid_on" value="{{ old('paid_on', $today->toDateString()) }}" max="{{ $today->toDateString() }}" class="input" required>@error('paid_on')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="pv_mode">{{ __('From') }}</label>
    <select id="pv_mode" name="mode" class="input"><option value="cash">{{ __('Office cash') }}</option><option value="bank" @selected(old('mode') === 'bank')>{{ __('Bank') }}</option></select></div>
  <div><label class="label" for="pv_bank">{{ __('Bank account') }}</label>
    <select id="pv_bank" name="bank_account_id" class="input"><option value="">{{ __('The default one') }}</option>@foreach ($banks as $b)<option value="{{ $b->id }}" @selected(old('bank_account_id') == $b->id)>{{ $b->label }} {{ $b->masked() }}</option>@endforeach</select></div>
  <div><label class="label" for="pv_ref">{{ __('UTR / cheque no.') }}</label><input id="pv_ref" name="reference" value="{{ old('reference') }}" class="input" maxlength="60">@error('reference')<span class="error">{{ $message }}</span>@enderror</div>
  <div class="sm:col-span-2"><label class="label" for="pv_narr">{{ __('For') }}</label><input id="pv_narr" name="narration" value="{{ old('narration') }}" class="input" maxlength="255" required placeholder="{{ __('e.g. Diesel bill for October') }}">@error('narration')<span class="error">{{ $message }}</span>@enderror</div>
  <div class="text-end sm:col-span-2 xl:col-span-3"><button class="btn-primary"><x-icon name="check" /> {{ __('Post the payment') }}</button></div>
</form>
@endsection
