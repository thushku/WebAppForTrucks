@extends('layouts.app')
@section('title', $person->exists ? __('Edit :name', ['name' => $person->name]) : __('Add person'))
@section('content')
@php($p = $person)
<form method="POST" action="{{ $p->exists ? route('people.update', $p) : route('people.store') }}" class="mx-auto max-w-4xl space-y-6" novalidate autocomplete="off">
  @csrf
  @if ($p->exists) @method('PUT') @endif

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Person') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div class="sm:col-span-2"><label class="label" for="name">{{ __('Name') }}</label><input id="name" name="name" class="input" value="{{ old('name', $p->name) }}" maxlength="150" required>@error('name')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="type">{{ __('Works as') }}</label><select id="type" name="type" class="input">@foreach (\App\Modules\People\Models\Person::TYPES as $v => $label)<option value="{{ $v }}" @selected(old('type', $p->type) === $v)>{{ __($label) }}</option>@endforeach</select></div>
      <div>
        <label class="label" for="mobile">{{ __('Mobile') }}</label>
        <input id="mobile" name="mobile" class="input" value="{{ old('mobile', $p->mobile) }}" maxlength="20" inputmode="tel">
        <span class="hint">{{ __('The crew app signs in with this number.') }}</span>
        @error('mobile')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div><label class="label" for="alt_mobile">{{ __('Second mobile') }}</label><input id="alt_mobile" name="alt_mobile" class="input" value="{{ old('alt_mobile', $p->alt_mobile) }}" maxlength="20" inputmode="tel">@error('alt_mobile')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="employee_code">{{ __('Employee code') }}</label><input id="employee_code" name="employee_code" class="input" value="{{ old('employee_code', $p->employee_code) }}" maxlength="20">@error('employee_code')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="date_of_birth">{{ __('Date of birth') }}</label><input id="date_of_birth" name="date_of_birth" type="date" class="input" value="{{ old('date_of_birth', $p->date_of_birth?->toDateString()) }}">@error('date_of_birth')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="joining_date">{{ __('Joined on') }}</label><input id="joining_date" name="joining_date" type="date" class="input" value="{{ old('joining_date', $p->joining_date?->toDateString()) }}"></div>
      <div>
        <label class="label" for="status">{{ __('Status') }}</label>
        <select id="status" name="status" class="input">@foreach (\App\Modules\People\Models\Person::STATUSES as $v => $label)<option value="{{ $v }}" @selected(old('status', $p->status) === $v)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div><label class="label" for="branch_id">{{ __('Branch') }}</label><select id="branch_id" name="branch_id" class="input"><option value="">—</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id', $p->branch_id) === $id)>{{ $name }}</option>@endforeach</select></div>
      <div><label class="label" for="default_truck_id">{{ __('Usual truck') }}</label><select id="default_truck_id" name="default_truck_id" class="input"><option value="">—</option>@foreach ($trucks as $id => $reg)<option value="{{ $id }}" @selected((int) old('default_truck_id', $p->default_truck_id) === $id)>{{ $reg }}</option>@endforeach</select></div>
      <div><label class="label" for="leaving_date">{{ __('Left on') }}</label><input id="leaving_date" name="leaving_date" type="date" class="input" value="{{ old('leaving_date', $p->leaving_date?->toDateString()) }}">@error('leaving_date')<span class="error">{{ $message }}</span>@enderror</div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Driving licence') }}</h2>
    @if ($licenceDoc)<p class="mt-1 text-sm text-ink-500">{{ __('The number and expiry come from the licence in Documents.') }} <a href="{{ route('documents.show', $licenceDoc) }}" class="font-semibold text-brand-700 hover:underline">{{ __('Open it to renew') }}</a></p>@endif
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-2"><label class="label" for="licence_no">{{ __('Licence no.') }}</label><input id="licence_no" name="licence_no" class="input font-mono uppercase" value="{{ old('licence_no', $p->licence_no) }}" maxlength="30" @disabled($licenceDoc)>@error('licence_no')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="licence_class">{{ __('Class') }}</label><input id="licence_class" name="licence_class" class="input" value="{{ old('licence_class', $p->licence_class) }}" maxlength="40" placeholder="{{ $country === 'AE' ? __('Heavy vehicle') : __('Transport (HGMV)') }}"></div>
      <div><label class="label" for="licence_expiry">{{ __('Valid until') }}</label><input id="licence_expiry" name="licence_expiry" type="date" class="input" value="{{ old('licence_expiry', $p->licence_expiry?->toDateString()) }}" @disabled($licenceDoc)></div>
      <div><label class="label" for="badge_no">{{ __('Badge no.') }}</label><input id="badge_no" name="badge_no" class="input" value="{{ old('badge_no', $p->badge_no) }}" maxlength="30"></div>
      <label class="flex items-center gap-2 self-end pb-3 text-sm sm:col-span-2"><input type="checkbox" name="hazardous_endorsement" value="1" class="size-4 rounded border-ink-300" @checked(old('hazardous_endorsement', $p->hazardous_endorsement))> {{ __('May carry hazardous goods') }}</label>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Pay') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div><label class="label" for="pay_basis">{{ __('Paid by') }}</label><select id="pay_basis" name="pay_basis" class="input">@foreach (\App\Modules\People\Models\Person::PAY_BASES as $v => $label)<option value="{{ $v }}" @selected(old('pay_basis', $p->pay_basis) === $v)>{{ __($label) }}</option>@endforeach</select></div>
      <div><label class="label" for="monthly_salary">{{ __('Monthly salary') }}</label><input id="monthly_salary" name="monthly_salary" type="number" min="0" step="0.01" class="input" value="{{ old('monthly_salary', $p->monthly_salary) }}"></div>
      <div><label class="label" for="per_trip_rate">{{ __('Per trip') }}</label><input id="per_trip_rate" name="per_trip_rate" type="number" min="0" step="0.01" class="input" value="{{ old('per_trip_rate', $p->per_trip_rate) }}"></div>
      <div><label class="label" for="per_km_rate">{{ __('Per km') }}</label><input id="per_km_rate" name="per_km_rate" type="number" min="0" step="0.01" class="input" value="{{ old('per_km_rate', $p->per_km_rate) }}"></div>
    </div>
    <p class="hint">{{ __('Bata (daily allowance) follows the company rule in Settings → Trip rules.') }}</p>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Address and emergency contact') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div class="sm:col-span-3"><label class="label" for="address">{{ __('Address') }}</label><textarea id="address" name="address" rows="2" class="input" maxlength="500">{{ old('address', $p->address) }}</textarea></div>
      <div><label class="label" for="emergency_name">{{ __('Emergency contact') }}</label><input id="emergency_name" name="emergency_name" class="input" value="{{ old('emergency_name', $p->emergency_name) }}" maxlength="120"></div>
      <div><label class="label" for="emergency_mobile">{{ __('Their mobile') }}</label><input id="emergency_mobile" name="emergency_mobile" class="input" value="{{ old('emergency_mobile', $p->emergency_mobile) }}" maxlength="20" inputmode="tel">@error('emergency_mobile')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="blood_group">{{ __('Blood group') }}</label><select id="blood_group" name="blood_group" class="input"><option value="">—</option>@foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)<option value="{{ $bg }}" @selected(old('blood_group', $p->blood_group) === $bg)>{{ $bg }}</option>@endforeach</select></div>
    </div>
  </section>

  @can('people.id_documents')
  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('ID and bank') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('Stored encrypted. Others see only the last 4 characters. Leave a number empty to keep the one on file.') }}</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="id_type">{{ __('ID type') }}</label><select id="id_type" name="id_type" class="input"><option value="">—</option>@foreach (\App\Modules\People\Models\Person::ID_TYPES as $v => $label)<option value="{{ $v }}" @selected(old('id_type', $p->id_type) === $v)>{{ __($label) }}</option>@endforeach</select></div>
      <div class="sm:col-span-2"><label class="label" for="id_number">{{ __('ID number') }}</label><input id="id_number" name="id_number" class="input font-mono" value="" maxlength="30" placeholder="{{ $p->maskedId() }}">@error('id_number')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="bank_account_holder">{{ __('Account holder') }}</label><input id="bank_account_holder" name="bank_account_holder" class="input" value="{{ old('bank_account_holder', $p->bank_account_holder) }}" maxlength="150"></div>
      <div><label class="label" for="bank_name">{{ __('Bank') }}</label><input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $p->bank_name) }}" maxlength="120"></div>
      @if ($country === 'IN')
        <div><label class="label" for="bank_ifsc">IFSC</label><input id="bank_ifsc" name="bank_ifsc" class="input font-mono uppercase" value="{{ old('bank_ifsc', $p->bank_ifsc) }}" maxlength="11">@error('bank_ifsc')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="bank_account_number">{{ __('Account number') }}</label><input id="bank_account_number" name="bank_account_number" class="input font-mono" value="" maxlength="18" inputmode="numeric" placeholder="{{ $p->bank_last4 ? '••••' . $p->bank_last4 : '' }}">@error('bank_account_number')<span class="error">{{ $message }}</span>@enderror</div>
      @else
        <div><label class="label" for="bank_iban">IBAN</label><input id="bank_iban" name="bank_iban" class="input font-mono uppercase" value="" maxlength="34" placeholder="{{ $p->bank_last4 ? '••••' . $p->bank_last4 : '' }}">@error('bank_iban')<span class="error">{{ $message }}</span>@enderror</div>
      @endif
      <div><label class="label" for="upi_id">{{ __('UPI id') }}</label><input id="upi_id" name="upi_id" class="input" value="" maxlength="100">@error('upi_id')<span class="error">{{ $message }}</span>@enderror</div>
    </div>
  </section>
  @endcan

  <section class="card p-6">
    <label class="label" for="notes">{{ __('Notes') }}</label>
    <textarea id="notes" name="notes" rows="2" class="input" maxlength="2000">{{ old('notes', $p->notes) }}</textarea>
  </section>

  <div class="flex justify-end gap-2">
    <a href="{{ $p->exists ? route('people.show', $p) : route('people.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ $p->exists ? __('Save') : __('Add person') }}</button>
  </div>
</form>
@endsection
