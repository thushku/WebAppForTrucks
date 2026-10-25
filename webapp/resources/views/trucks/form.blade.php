@extends('layouts.app')
@section('title', $truck->exists ? __('Edit :reg', ['reg' => $truck->reg_no]) : __('Add truck'))
@section('content')
@php($t = $truck)
<form method="POST" action="{{ $t->exists ? route('trucks.update', $t) : route('trucks.store') }}" class="mx-auto max-w-4xl space-y-6" novalidate>
  @csrf
  @if ($t->exists) @method('PUT') @endif

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Registration') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="reg_no">{{ __('Registration no.') }}</label><input id="reg_no" name="reg_no" class="input font-mono uppercase" value="{{ old('reg_no', $t->reg_no) }}" maxlength="20" placeholder="KL-07-AB-1234" required>@error('reg_no')<span class="error">{{ $message }}</span>@enderror</div>
      <div class="sm:col-span-2"><label class="label" for="rc_owner_name">{{ __('RC owner name') }}</label><input id="rc_owner_name" name="rc_owner_name" class="input" value="{{ old('rc_owner_name', $t->rc_owner_name) }}" maxlength="150" required>@error('rc_owner_name')<span class="error">{{ $message }}</span>@enderror</div>
      <div>
        <label class="label" for="registration_state">{{ \App\Support\Regions::label($country) }}</label>
        <select id="registration_state" name="registration_state" class="input"><option value="">—</option>@foreach ($regions as $code => $name)<option value="{{ $code }}" @selected(old('registration_state', $t->registration_state) === (string) $code)>{{ $name }}</option>@endforeach</select>
      </div>
      <div><label class="label" for="registered_rto">{{ $country === 'AE' ? __('Licensing authority') : __('RTO') }}</label><input id="registered_rto" name="registered_rto" class="input" value="{{ old('registered_rto', $t->registered_rto) }}" maxlength="80"></div>
      <div><label class="label" for="registration_date">{{ __('Registration date') }}</label><input id="registration_date" name="registration_date" type="date" class="input" value="{{ old('registration_date', $t->registration_date?->toDateString()) }}">@error('registration_date')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="vehicle_class">{{ __('Vehicle class') }}</label><input id="vehicle_class" name="vehicle_class" class="input" value="{{ old('vehicle_class', $t->vehicle_class) }}" maxlength="60" placeholder="{{ __('Goods carrier') }}"></div>
      <div><label class="label" for="chassis_no">{{ __('Chassis no.') }}</label><input id="chassis_no" name="chassis_no" class="input font-mono uppercase" value="{{ old('chassis_no', $t->chassis_no) }}" maxlength="30">@error('chassis_no')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="engine_no">{{ __('Engine no.') }}</label><input id="engine_no" name="engine_no" class="input font-mono uppercase" value="{{ old('engine_no', $t->engine_no) }}" maxlength="30">@error('engine_no')<span class="error">{{ $message }}</span>@enderror</div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Build and capacity') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div><label class="label" for="make">{{ __('Make') }}</label><input id="make" name="make" class="input" value="{{ old('make', $t->make) }}" maxlength="60" placeholder="Tata"></div>
      <div><label class="label" for="model">{{ __('Model') }}</label><input id="model" name="model" class="input" value="{{ old('model', $t->model) }}" maxlength="80" placeholder="Signa 4825.TK"></div>
      <div><label class="label" for="manufacture_year">{{ __('Year') }}</label><input id="manufacture_year" name="manufacture_year" type="number" class="input" value="{{ old('manufacture_year', $t->manufacture_year) }}" min="1980" max="{{ now()->year + 1 }}">@error('manufacture_year')<span class="error">{{ $message }}</span>@enderror</div>
      <div>
        <label class="label" for="emission_norm">{{ __('Emission norm') }}</label>
        <select id="emission_norm" name="emission_norm" class="input"><option value="">—</option>@foreach (\App\Modules\Fleet\Models\Truck::NORMS as $n)<option value="{{ $n }}" @selected(old('emission_norm', $t->emission_norm) === $n)>{{ $n }}</option>@endforeach</select>
      </div>
      <div>
        <label class="label" for="truck_type">{{ __('Truck type') }}</label>
        <select id="truck_type" name="truck_type" class="input"><option value="">—</option>@foreach ($types as $ty)<option value="{{ $ty->code }}" @selected(old('truck_type', $t->truck_type) === $ty->code)>{{ $ty->name }}</option>@endforeach</select>
      </div>
      <div>
        <label class="label" for="body_type">{{ __('Body') }}</label>
        <select id="body_type" name="body_type" class="input"><option value="">—</option>@foreach ($bodies as $code => $name)<option value="{{ $code }}" @selected(old('body_type', $t->body_type) === $code)>{{ $name }}</option>@endforeach</select>
      </div>
      <div>
        <label class="label" for="fuel_type">{{ __('Fuel') }}</label>
        <select id="fuel_type" name="fuel_type" class="input">@foreach (\App\Modules\Fleet\Models\Truck::FUELS as $v => $label)<option value="{{ $v }}" @selected(old('fuel_type', $t->fuel_type) === $v)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div><label class="label" for="wheels">{{ __('Wheels') }}</label><input id="wheels" name="wheels" type="number" class="input" value="{{ old('wheels', $t->wheels) }}" min="3" max="40"></div>
      <div><label class="label" for="axle_config">{{ __('Axles') }}</label><input id="axle_config" name="axle_config" class="input" value="{{ old('axle_config', $t->axle_config) }}" maxlength="20" placeholder="8x2"></div>
      <div><label class="label" for="deck_length_ft">{{ __('Deck length (ft)') }}</label><input id="deck_length_ft" name="deck_length_ft" type="number" step="0.1" class="input" value="{{ old('deck_length_ft', $t->deck_length_ft) }}"></div>
      <div><label class="label" for="rate_card_label">{{ __('Size label') }}</label><input id="rate_card_label" name="rate_card_label" class="input" value="{{ old('rate_card_label', $t->rate_card_label) }}" maxlength="40" placeholder="32 ft MXL"></div>
      <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="lift_axle" value="1" class="size-4 rounded border-ink-300" @checked(old('lift_axle', $t->lift_axle))> {{ __('Lift axle') }}</label>
      <div><label class="label" for="gvw_kg">{{ __('Gross weight (kg)') }}</label><input id="gvw_kg" name="gvw_kg" type="number" class="input" value="{{ old('gvw_kg', $t->gvw_kg) }}"></div>
      <div><label class="label" for="unladen_kg">{{ __('Unladen weight (kg)') }}</label><input id="unladen_kg" name="unladen_kg" type="number" class="input" value="{{ old('unladen_kg', $t->unladen_kg) }}">@error('unladen_kg')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="payload_mt">{{ __('Payload (MT)') }}</label><input id="payload_mt" name="payload_mt" type="number" step="0.001" class="input" value="{{ old('payload_mt', $t->payload_mt) }}"></div>
      <div><label class="label" for="fuel_tank_l">{{ __('Fuel tank (litres)') }}</label><input id="fuel_tank_l" name="fuel_tank_l" type="number" class="input" value="{{ old('fuel_tank_l', $t->fuel_tank_l) }}"></div>
      <div><label class="label" for="mileage_loaded_kmpl">{{ __('Mileage loaded (km/l)') }}</label><input id="mileage_loaded_kmpl" name="mileage_loaded_kmpl" type="number" step="0.01" class="input" value="{{ old('mileage_loaded_kmpl', $t->mileage_loaded_kmpl) }}"></div>
      <div><label class="label" for="mileage_empty_kmpl">{{ __('Mileage empty (km/l)') }}</label><input id="mileage_empty_kmpl" name="mileage_empty_kmpl" type="number" step="0.01" class="input" value="{{ old('mileage_empty_kmpl', $t->mileage_empty_kmpl) }}"></div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Ownership and loan') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-2">
        <label class="label" for="ownership">{{ __('Ownership') }}</label>
        <select id="ownership" name="ownership" class="input">@foreach (\App\Modules\Fleet\Models\Truck::OWNERSHIP as $v => $label)<option value="{{ $v }}" @selected(old('ownership', $t->ownership) === $v)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div class="sm:col-span-2">
        <label class="label" for="owner_party_id">{{ __('Owner (attached truck)') }}</label>
        <select id="owner_party_id" name="owner_party_id" class="input"><option value="">—</option>@foreach ($owners as $id => $name)<option value="{{ $id }}" @selected((int) old('owner_party_id', $t->owner_party_id) === $id)>{{ $name }}</option>@endforeach</select>
        @error('owner_party_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div><label class="label" for="financier">{{ __('Financier') }}</label><input id="financier" name="financier" class="input" value="{{ old('financier', $t->financier) }}" maxlength="120"></div>
      <div><label class="label" for="emi_amount">{{ __('EMI') }}</label><input id="emi_amount" name="emi_amount" type="number" step="0.01" class="input" value="{{ old('emi_amount', $t->emi_amount) }}"></div>
      <div><label class="label" for="emi_day">{{ __('EMI day of month') }}</label><input id="emi_day" name="emi_day" type="number" min="1" max="31" class="input" value="{{ old('emi_day', $t->emi_day) }}"></div>
      <div><label class="label" for="loan_ends_on">{{ __('Loan ends') }}</label><input id="loan_ends_on" name="loan_ends_on" type="date" class="input" value="{{ old('loan_ends_on', $t->loan_ends_on?->toDateString()) }}"></div>
      <div><label class="label" for="purchase_date">{{ __('Bought on') }}</label><input id="purchase_date" name="purchase_date" type="date" class="input" value="{{ old('purchase_date', $t->purchase_date?->toDateString()) }}"></div>
      <div><label class="label" for="purchase_price">{{ __('Price') }}</label><input id="purchase_price" name="purchase_price" type="number" step="0.01" class="input" value="{{ old('purchase_price', $t->purchase_price) }}"></div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Running') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div>
        <label class="label" for="odometer_km">{{ __('Odometer (km)') }}</label>
        <input id="odometer_km" name="odometer_km" type="number" min="0" class="input" value="{{ old('odometer_km', $t->odometer_km) }}" @disabled($t->exists && $t->odometer_km !== null)>
        @if ($t->exists && $t->odometer_km !== null)<span class="hint">{{ __('Moves forward with trips and service jobs.') }}</span>@endif
      </div>
      <div><label class="label" for="fastag_no">{{ $country === 'AE' ? __('Salik / Darb tag') : __('FASTag no.') }}</label><input id="fastag_no" name="fastag_no" class="input" value="{{ old('fastag_no', $t->fastag_no) }}" maxlength="40"></div>
      <div><label class="label" for="tracker_device_id">{{ __('GPS tracker id') }}</label><input id="tracker_device_id" name="tracker_device_id" class="input" value="{{ old('tracker_device_id', $t->tracker_device_id) }}" maxlength="60"></div>
      <div>
        <label class="label" for="branch_id">{{ __('Branch') }}</label>
        <select id="branch_id" name="branch_id" class="input"><option value="">—</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id', $t->branch_id) === $id)>{{ $name }}</option>@endforeach</select>
      </div>
    </div>
    <div class="mt-4"><label class="label" for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes" rows="2" class="input" maxlength="2000">{{ old('notes', $t->notes) }}</textarea></div>
  </section>

  <div class="flex justify-end gap-2">
    <a href="{{ $t->exists ? route('trucks.show', $t) : route('trucks.index') }}" class="btn-ghost">{{ __('Cancel') }}</a>
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ $t->exists ? __('Save') : __('Add truck') }}</button>
  </div>
</form>
@endsection
