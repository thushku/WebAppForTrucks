@extends('layouts.app')
@section('title', $consignment->exists ? ($consignment->lr_number ?? __('Load')) : __('Add a load'))
@section('content')
@php
  $c = $consignment;
  $locked = $c->exists && ($trip->status === 'running' || ! $trip->isPlanning() || $c->status === 'cancelled');
@endphp
<a href="{{ route('trips.show', $trip) }}" class="btn-ghost"><x-icon name="arrow-left" /> <span class="docno font-mono">{{ $trip->label }}</span></a>
<form method="POST" action="{{ $c->exists ? route('trips.loads.update', [$trip, $c]) : route('trips.loads.store', $trip) }}" class="mx-auto max-w-5xl space-y-6" data-trip-form novalidate>
  @csrf
  @if ($c->exists) @method('PUT') @endif
  {{-- The trip's route and truck, for the rate lookup. --}}
  <input type="hidden" data-rate-from value="{{ $trip->from_place_id }}">
  <input type="hidden" data-rate-to value="{{ $trip->to_place_id }}">
  <input type="hidden" data-rate-truck value="{{ $trip->truck_id }}">
  <input type="hidden" data-rate-date value="{{ $trip->trip_date->toDateString() }}">
  <input type="hidden" data-calc-km value="{{ $trip->planned_km }}">
  @error('consignment')<p class="error">{{ $message }}</p>@enderror
  <fieldset @disabled($locked) class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ $c->lr_number ? __('LR :no', ['no' => $c->lr_number]) : __('New load') }}</h2>
        @if ($locked)<span class="text-sm text-ink-500">{{ __('Fixed: the trip has started.') }}</span>@endif
      </div>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="pickup_stop_id">{{ __('Picked up at') }}</label>
          <select id="pickup_stop_id" name="pickup_stop_id" class="input">@foreach ($trip->stops as $s)<option value="{{ $s->id }}" @selected((int) old('pickup_stop_id', $c->pickup_stop_id) === $s->id)>{{ $s->seq }}. {{ $s->place->name }}</option>@endforeach</select>
          @error('pickup_stop_id')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="label" for="drop_stop_id">{{ __('Dropped at') }}</label>
          <select id="drop_stop_id" name="drop_stop_id" class="input">@foreach ($trip->stops as $s)<option value="{{ $s->id }}" @selected((int) old('drop_stop_id', $c->drop_stop_id) === $s->id)>{{ $s->seq }}. {{ $s->place->name }}</option>@endforeach</select>
          @error('drop_stop_id')<span class="error">{{ $message }}</span>@enderror
        </div>
      </div>
      <div class="mt-4">@include('trips.partials.load-fields', ['n' => '', 'c' => $c, 'gstDefault' => $c->gst_payable_by])</div>
      <div class="mt-4"><label class="label" for="c_remarks">{{ __('Remarks') }}</label><textarea id="c_remarks" name="remarks" rows="2" class="input" maxlength="2000">{{ old('remarks', $c->remarks) }}</textarea></div>
    </section>
  </fieldset>
  @unless ($locked)
    <div class="flex justify-end gap-2">
      <a href="{{ route('trips.show', $trip) }}" class="btn-ghost">{{ __('Cancel') }}</a>
      <button class="btn-primary"><x-icon name="check" /> {{ __('Save load') }}</button>
    </div>
  @endunless
</form>

@if ($c->exists && $c->status !== 'cancelled' && $trip->isPlanning())
  <form method="POST" action="{{ route('trips.loads.cancel', [$trip, $c]) }}" class="mx-auto flex max-w-5xl flex-wrap items-end justify-end gap-2" data-confirm="{{ __('Cancel this load? Its LR number stays used.') }}">
    @csrf
    <div><label class="label" for="lc_reason">{{ __('Cancel the load') }}</label><input id="lc_reason" name="reason" class="input w-72" maxlength="200" placeholder="{{ __('Why') }}"></div>
    <button class="btn-danger">{{ __('Cancel load') }}</button>
    @error('reason')<span class="error w-full text-end">{{ $message }}</span>@enderror
  </form>
@endif
@endsection
