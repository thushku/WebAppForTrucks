@extends('layouts.app')
@section('title', $creating ? __('New trip') : __('Edit :trip', ['trip' => $trip->label]))
@section('content')
@php
  $t = $trip;
  $dt = fn ($v) => $v instanceof \Carbon\CarbonInterface ? $v->format('Y-m-d\TH:i') : $v;
  $source = old('truck_source', $t->truck_source);
@endphp
<form method="POST" action="{{ $creating ? route('trips.store') : route('trips.update', $t) }}" class="mx-auto max-w-5xl space-y-6" data-trip-form novalidate>
  @csrf
  @if (! $creating) @method('PUT') @endif
  @error('crew')<p class="error">{{ $message }}</p>@enderror
  @error('trip')<p class="error">{{ $message }}</p>@enderror

  <section class="card p-6">
    <div class="grid gap-4 sm:grid-cols-4">
      <div><label class="label" for="trip_date">{{ __('Trip date') }}</label><input id="trip_date" name="trip_date" type="date" class="input" value="{{ old('trip_date', $t->trip_date?->toDateString() ?? $t->trip_date) }}" required data-rate-date>@error('trip_date')<span class="error">{{ $message }}</span>@enderror</div>
      <div>
        <label class="label" for="load_type">{{ __('Load') }}</label>
        <select id="load_type" name="load_type" class="input">@foreach (\App\Modules\Trips\Models\Trip::LOAD_TYPES as $v => $label)<option value="{{ $v }}" @selected(old('load_type', $t->load_type) === $v)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div>
        <label class="label" for="trip_kind">{{ __('Kind') }}</label>
        <select id="trip_kind" name="trip_kind" class="input" data-trip-kind>@foreach (\App\Modules\Trips\Models\Trip::KINDS as $v => $label)<option value="{{ $v }}" @selected(old('trip_kind', $t->trip_kind) === $v)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div>
        <label class="label" for="branch_id">{{ __('Branch') }}</label>
        <select id="branch_id" name="branch_id" class="input"><option value="">—</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id', $t->branch_id) === $id)>{{ $name }}</option>@endforeach</select>
      </div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Truck and crew') }}</h2>
    <div class="mt-3 flex gap-5 text-sm" role="radiogroup" aria-label="{{ __('Truck') }}">
      <label class="flex items-center gap-2"><input type="radio" name="truck_source" value="own" class="size-4" data-truck-source @checked($source === 'own')> {{ __('Our truck') }}</label>
      <label class="flex items-center gap-2"><input type="radio" name="truck_source" value="hired" class="size-4" data-truck-source @checked($source === 'hired')> {{ __('Hired truck (market)') }}</label>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
      <div class="{{ $source === 'own' ? '' : 'hidden' }}" data-source-own>
        <label class="label" for="truck_id">{{ __('Truck') }}</label>
        <select id="truck_id" name="truck_id" class="input" data-rate-truck>
          <option value="">—</option>
          @foreach ($trucks as $tr)
            @php($busy = $busyTrucks[$tr->id] ?? null)
            <option value="{{ $tr->id }}" @selected((int) old('truck_id', $t->truck_id) === $tr->id)>{{ $tr->reg_no }}{{ $busy ? ' — ' . __('on :trip', ['trip' => $busy]) : ($tr->status !== 'idle' ? ' — ' . __(\App\Modules\Fleet\Models\Truck::STATUSES[$tr->status]) : '') }}</option>
          @endforeach
        </select>
        @can('truck.manage')<button type="button" class="mt-1 text-xs font-semibold text-brand-700 hover:underline" data-quick-open="truck">+ {{ __('New truck') }}</button>@endcan
        @error('truck_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="{{ $source === 'hired' ? '' : 'hidden' }}" data-source-hired>
        <label class="label" for="supplier_party_id">{{ __('Supplier') }}</label>
        <select id="supplier_party_id" name="supplier_party_id" class="input" data-supplier>
          <option value="">—</option>
          @foreach ($suppliers as $id => $name)<option value="{{ $id }}" @selected((int) old('supplier_party_id', $t->supplier_party_id) === $id)>{{ $name }}</option>@endforeach
        </select>
        @error('supplier_party_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="{{ $source === 'hired' ? '' : 'hidden' }}" data-source-hired>
        <label class="label" for="supplier_vehicle_id">{{ __('Hired truck') }}</label>
        <select id="supplier_vehicle_id" name="supplier_vehicle_id" class="input" data-supplier-vehicle>
          <option value="">—</option>
          @foreach ($vehicles as $v)<option value="{{ $v->id }}" data-party="{{ $v->party_id }}" @selected((int) old('supplier_vehicle_id', $t->supplier_vehicle_id) === $v->id)>{{ $v->reg_no }}{{ $v->capacity_mt ? ' · ' . rtrim(rtrim((string) $v->capacity_mt, '0'), '.') . ' MT' : '' }}</option>@endforeach
        </select>
        @error('supplier_vehicle_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="driver_id">{{ __('Driver') }}</label>
        <select id="driver_id" name="driver_id" class="input">
          <option value="">{{ $source === 'hired' ? __('The supplier’s driver') : '—' }}</option>
          @foreach ($drivers as $p)<option value="{{ $p->id }}" @selected((int) old('driver_id', $crewNow['driver']) === $p->id)>{{ $p->name }}{{ $p->licence_expiry && $p->licence_expiry->isPast() ? ' — ' . __('licence expired') : '' }}</option>@endforeach
        </select>
        @can('people.manage')<button type="button" class="mt-1 text-xs font-semibold text-brand-700 hover:underline" data-quick-open="person" data-quick-type="driver">+ {{ __('New driver') }}</button>@endcan
        @error('driver_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="co_driver_id">{{ __('Co-driver') }}</label>
        <select id="co_driver_id" name="co_driver_id" class="input"><option value="">—</option>@foreach ($drivers as $p)<option value="{{ $p->id }}" @selected((int) old('co_driver_id', $crewNow['co_driver']) === $p->id)>{{ $p->name }}</option>@endforeach</select>
        @error('co_driver_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="helper_ids">{{ __('Helpers') }}</label>
        <select id="helper_ids" name="helper_ids[]" class="input min-h-[2.75rem]" multiple size="3">
          @foreach ($helpers as $p)<option value="{{ $p->id }}" @selected(in_array($p->id, array_map('intval', old('helper_ids', $crewNow['helpers'])), true))>{{ $p->name }}</option>@endforeach
        </select>
        <span class="hint">{{ __('Ctrl / ⌘ + click for more than one.') }}</span>
      </div>
    </div>
  </section>

  <section class="card p-6">
    <h2 class="text-lg font-semibold">{{ __('Route and plan') }}</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div>
        <label class="label" for="from_place_id">{{ __('From') }}</label>
        <select id="from_place_id" name="from_place_id" class="input" data-rate-from required><option value="">—</option>@foreach ($places as $p)<option value="{{ $p->id }}" @selected((int) old('from_place_id', $t->from_place_id) === $p->id)>{{ $p->name }} ({{ $p->short_code }})</option>@endforeach</select>
        @can('settings.masters')<button type="button" class="mt-1 text-xs font-semibold text-brand-700 hover:underline" data-quick-open="place">+ {{ __('New place') }}</button>@endcan
        @error('from_place_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="to_place_id">{{ __('To') }}</label>
        <select id="to_place_id" name="to_place_id" class="input" data-rate-to required><option value="">—</option>@foreach ($places as $p)<option value="{{ $p->id }}" @selected((int) old('to_place_id', $t->to_place_id) === $p->id)>{{ $p->name }} ({{ $p->short_code }})</option>@endforeach</select>
        @error('to_place_id')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div><label class="label" for="planned_km">{{ __('Planned km') }}</label><input id="planned_km" name="planned_km" type="number" min="1" class="input" value="{{ old('planned_km', $t->planned_km) }}" data-calc-km></div>
      <div>
        <label class="label" for="return_of_trip_id">{{ __('Return of trip') }}</label>
        <select id="return_of_trip_id" name="return_of_trip_id" class="input"><option value="">—</option>@foreach ($returnable as $id => $no)<option value="{{ $id }}" @selected((int) old('return_of_trip_id', $t->return_of_trip_id) === $id)>{{ $no }}</option>@endforeach</select>
      </div>
      <div><label class="label" for="planned_start_at">{{ __('Planned start') }}</label><input id="planned_start_at" name="planned_start_at" type="datetime-local" class="input" value="{{ old('planned_start_at', $dt($t->planned_start_at)) }}"></div>
      <div><label class="label" for="expected_delivery_at">{{ __('Expected delivery') }}</label><input id="expected_delivery_at" name="expected_delivery_at" type="datetime-local" class="input" value="{{ old('expected_delivery_at', $dt($t->expected_delivery_at)) }}">@error('expected_delivery_at')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="budget_fuel">{{ __('Fuel budget') }}</label><input id="budget_fuel" name="budget_fuel" type="number" min="0" step="0.01" class="input" value="{{ old('budget_fuel', $t->budget_fuel) }}"></div>
      <div><label class="label" for="budget_toll">{{ __('Toll budget') }}</label><input id="budget_toll" name="budget_toll" type="number" min="0" step="0.01" class="input" value="{{ old('budget_toll', $t->budget_toll) }}"></div>
    </div>
    @if (! $creating && $t->load_type !== 'ftl')<p class="hint">{{ __('Stops between from and to are added on the trip page.') }}</p>@endif
  </section>

  @if ($creating)
    <section class="card p-6 {{ old('trip_kind', $t->trip_kind) === 'empty' ? 'hidden' : '' }}" data-first-load>
      <h2 class="text-lg font-semibold">{{ $t->load_type === 'ftl' ? __('The load') : __('First load') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('The agreed rate fills in when the party, route and material have an approved rate contract.') }}</p>
      <div class="mt-4">@include('trips.partials.load-fields', ['n' => 'load', 'c' => $prefill ?? null])</div>
    </section>
  @endif

  <section class="card p-6">
    <div class="grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="other_charges">{{ __('Other charges (billed to client)') }}</label><input id="other_charges" name="other_charges" type="number" min="0" step="0.01" class="input" value="{{ old('other_charges', $t->other_charges ?: '') }}"></div>
      <div class="sm:col-span-2"><label class="label" for="instructions">{{ __('Instructions to the crew') }}</label><textarea id="instructions" name="instructions" rows="2" class="input" maxlength="2000">{{ old('instructions', $t->instructions) }}</textarea></div>
      <div class="sm:col-span-3"><label class="label" for="office_note">{{ __('Office note') }} <span class="font-normal text-ink-500">— {{ __('the crew never see it') }}</span></label><textarea id="office_note" name="office_note" rows="2" class="input" maxlength="2000">{{ old('office_note', $t->office_note) }}</textarea></div>
    </div>
  </section>

  <div class="flex justify-end gap-2">
    <a href="{{ $creating ? route('trips.index') : route('trips.show', $t) }}" class="btn-ghost">{{ __('Cancel') }}</a>
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ $creating ? __('Save draft') : __('Save') }}</button>
  </div>
</form>
@include('trips.partials.quick-add')
@endsection
